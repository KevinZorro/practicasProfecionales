<?php

declare(strict_types=1);

use App\Enums\EstadoItemInventario;
use App\Enums\Rol;
use App\Exceptions\InventarioInvalido;
use App\Livewire\Inventario\ListadoInventario;
use App\Models\CambioEstadoItem;
use App\Models\ItemInventario;
use App\Models\User;
use App\Services\InventarioService;
use Database\Seeders\RolSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->servicio = app(InventarioService::class);
    $this->administrativo = User::factory()->administrativo()->create();
    $this->coordinadora = User::factory()->coordinador()->create();
});

/** Lleva el ítem hasta el estado pedido pasando por el flujo. */
function llevarA(ItemInventario $item, EstadoItemInventario $destino, User $actor): ItemInventario
{
    $servicio = app(InventarioService::class);

    $camino = match ($destino) {
        EstadoItemInventario::EnRevision => [EstadoItemInventario::EnRevision],
        EstadoItemInventario::Defectuoso => [EstadoItemInventario::EnRevision, EstadoItemInventario::Defectuoso],
        EstadoItemInventario::DadoDeBaja => [EstadoItemInventario::EnRevision, EstadoItemInventario::Defectuoso, EstadoItemInventario::DadoDeBaja],
        EstadoItemInventario::Operativo => [],
    };

    foreach ($camino as $paso) {
        $item = $servicio->cambiarEstado($actor, $item, $paso, 'Motivo de prueba para '.$paso->value);
    }

    return $item;
}

// ---------------------------------------------------------------------
// Transiciones válidas
// ---------------------------------------------------------------------

it('arranca operativo todo ítem nuevo', function (): void {
    expect(ItemInventario::factory()->create()->estado)->toBe(EstadoItemInventario::Operativo);
});

it('no deja que una asignación masiva cambie el estado', function (): void {
    // estado está fuera de fillable a propósito: si entrara por fill() se
    // colaría un cambio sin motivo, sin responsable y sin respetar el flujo.
    // Misma defensa que nivel_fidelidad con el RF39.
    $item = ItemInventario::factory()->create();

    $item->update(['estado' => EstadoItemInventario::DadoDeBaja]);

    expect($item->fresh()->estado)->toBe(EstadoItemInventario::Operativo)
        ->and(CambioEstadoItem::count())->toBe(0);
});

it('lleva un ítem de operativo a en revisión', function (): void {
    $item = ItemInventario::factory()->create();

    $item = $this->servicio->cambiarEstado(
        $this->administrativo,
        $item,
        EstadoItemInventario::EnRevision,
        'El balón de la sonda no infla.',
    );

    expect($item->fresh()->estado)->toBe(EstadoItemInventario::EnRevision)
        ->and($item->fresh()->activo)->toBeTrue();
});

it('confirma el defecto desde revisión', function (): void {
    $item = llevarA(ItemInventario::factory()->create(), EstadoItemInventario::EnRevision, $this->administrativo);

    $item = $this->servicio->cambiarEstado(
        $this->administrativo,
        $item,
        EstadoItemInventario::Defectuoso,
        'El filtro de la máscara de no reinhalación está roto.',
    );

    expect($item->fresh()->estado)->toBe(EstadoItemInventario::Defectuoso);
});

it('devuelve a operativo lo que resultó falsa alarma o se reparó', function (string $desde): void {
    $item = llevarA(ItemInventario::factory()->create(), EstadoItemInventario::from($desde), $this->administrativo);

    $item = $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::Operativo, 'Reparado en taller.');

    expect($item->fresh()->estado)->toBe(EstadoItemInventario::Operativo)
        ->and($item->fresh()->activo)->toBeTrue();
})->with(['en_revision', 'defectuoso']);

it('da de baja lo defectuoso sin arreglo y lo saca del catálogo', function (): void {
    $item = llevarA(ItemInventario::factory()->create(), EstadoItemInventario::Defectuoso, $this->administrativo);

    $item = $this->servicio->darDeBaja($this->coordinadora, $item, 'No hay repuesto del sensor.');

    expect($item->fresh()->estado)->toBe(EstadoItemInventario::DadoDeBaja)
        ->and($item->fresh()->activo)->toBeFalse()
        ->and(ItemInventario::find($item->id))->not->toBeNull();
});

// ---------------------------------------------------------------------
// Transiciones que el flujo no admite
// ---------------------------------------------------------------------

it('no deja saltarse la revisión para marcar un defecto', function (): void {
    // El cliente lo dijo así: el defecto se confirma revisando.
    $item = ItemInventario::factory()->create();

    expect(fn () => $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::Defectuoso, 'Está roto.'))
        ->toThrow(InventarioInvalido::class);

    expect($item->fresh()->estado)->toBe(EstadoItemInventario::Operativo);
});

it('no deja dar de baja un ítem operativo sin pasar por el flujo', function (): void {
    $item = ItemInventario::factory()->create();

    expect(fn () => $this->servicio->darDeBaja($this->coordinadora, $item, 'Ya no lo queremos.'))
        ->toThrow(InventarioInvalido::class);
});

it('no deja resucitar un ítem dado de baja', function (): void {
    $item = llevarA(ItemInventario::factory()->create(), EstadoItemInventario::DadoDeBaja, $this->coordinadora);

    expect(fn () => $this->servicio->cambiarEstado($this->coordinadora, $item, EstadoItemInventario::Operativo, 'Apareció uno nuevo.'))
        ->toThrow(InventarioInvalido::class, 'no vuelve a cambiar de estado');
});

// ---------------------------------------------------------------------
// Motivo y responsable
// ---------------------------------------------------------------------

it('registra motivo, responsable y estados del cambio', function (): void {
    $item = ItemInventario::factory()->create();

    $this->servicio->cambiarEstado(
        $this->administrativo,
        $item,
        EstadoItemInventario::EnRevision,
        'El balón de la sonda no infla.',
    );

    $cambio = $item->cambiosDeEstado()->first();

    expect($cambio->estado_anterior)->toBe(EstadoItemInventario::Operativo)
        ->and($cambio->estado_nuevo)->toBe(EstadoItemInventario::EnRevision)
        ->and($cambio->motivo)->toBe('El balón de la sonda no infla.')
        ->and($cambio->registrado_por)->toBe($this->administrativo->id);
});

it('guarda un registro por cada cambio, no solo el último', function (): void {
    // Es la razón de que el historial sea tabla y no columnas del ítem.
    $item = llevarA(ItemInventario::factory()->create(), EstadoItemInventario::DadoDeBaja, $this->coordinadora);

    expect($item->cambiosDeEstado()->count())->toBe(3)
        ->and($item->cambiosDeEstado()->pluck('estado_nuevo')->all())->toBe([
            EstadoItemInventario::DadoDeBaja,
            EstadoItemInventario::Defectuoso,
            EstadoItemInventario::EnRevision,
        ]);
});

it('conserva el historial de las idas y vueltas', function (): void {
    $item = ItemInventario::factory()->create();
    $item = $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::EnRevision, 'Hace un ruido raro.');
    $item = $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::Operativo, 'Falsa alarma: era el soporte.');
    $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::EnRevision, 'Volvió a hacerlo.');

    expect($item->cambiosDeEstado()->count())->toBe(3)
        ->and($item->fresh()->estado)->toBe(EstadoItemInventario::EnRevision);
});

it('no deja cambiar el estado sin motivo', function (string $motivo): void {
    $item = ItemInventario::factory()->create();

    expect(fn () => $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::EnRevision, $motivo))
        ->toThrow(InventarioInvalido::class, 'necesita un motivo');

    expect($item->fresh()->estado)->toBe(EstadoItemInventario::Operativo)
        ->and(CambioEstadoItem::count())->toBe(0);
})->with(['vacío' => '', 'solo espacios' => '   ']);

// ---------------------------------------------------------------------
// Disponibilidad (RF66.3)
// ---------------------------------------------------------------------

it('deja de contar como disponible en cuanto entra en revisión', function (): void {
    $item = ItemInventario::factory()->create(['cantidad_total' => 6]);

    expect($this->servicio->disponibilidadEnFranja($item, '2026-05-10', '07:00:00', '09:00:00'))->toBe(6);

    $item = $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::EnRevision, 'No infla.');

    expect($this->servicio->disponibilidadEnFranja($item, '2026-05-10', '07:00:00', '09:00:00'))->toBe(0);
});

it('no ofrece como disponible un ítem defectuoso ni uno dado de baja', function (string $estado): void {
    $item = llevarA(ItemInventario::factory()->create(['cantidad_total' => 6]), EstadoItemInventario::from($estado), $this->coordinadora);

    expect($this->servicio->disponibilidadEnFranja($item, '2026-05-10', '07:00:00', '09:00:00'))->toBe(0)
        ->and(ItemInventario::disponibles()->pluck('id')->all())->not->toContain($item->id);
})->with(['defectuoso', 'dado_de_baja']);

it('vuelve a contar como disponible cuando se repara', function (): void {
    $item = llevarA(ItemInventario::factory()->create(['cantidad_total' => 6]), EstadoItemInventario::Defectuoso, $this->administrativo);
    $item = $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::Operativo, 'Cambiada la pieza.');

    expect($this->servicio->disponibilidadEnFranja($item, '2026-05-10', '07:00:00', '09:00:00'))->toBe(6);
});

// ---------------------------------------------------------------------
// Permisos (RF66.4)
// ---------------------------------------------------------------------

it('deja cambiar el estado a quien gestiona el inventario', function (string $quien): void {
    $item = ItemInventario::factory()->create();

    $item = $this->servicio->cambiarEstado($this->$quien, $item, EstadoItemInventario::EnRevision, 'Suena raro.');

    expect($item->fresh()->estado)->toBe(EstadoItemInventario::EnRevision);
})->with(['administrativo', 'coordinadora']);

it('no deja a un docente ni a un estudiante cambiar el estado', function (Rol $rol): void {
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);
    $item = ItemInventario::factory()->create();

    expect(fn () => $this->servicio->cambiarEstado($usuario->fresh(), $item, EstadoItemInventario::EnRevision, 'Yo qué sé.'))
        ->toThrow(AuthorizationException::class);

    expect($item->fresh()->estado)->toBe(EstadoItemInventario::Operativo)
        ->and(CambioEstadoItem::count())->toBe(0);
})->with([Rol::Docente, Rol::Estudiante]);

it('no deja al administrativo dar de baja, que es lo único reservado', function (): void {
    $item = llevarA(ItemInventario::factory()->create(), EstadoItemInventario::Defectuoso, $this->administrativo);

    expect(fn () => $this->servicio->darDeBaja($this->administrativo, $item, 'Ya no sirve.'))
        ->toThrow(AuthorizationException::class);

    expect($item->fresh()->estado)->toBe(EstadoItemInventario::Defectuoso);
});

// ---------------------------------------------------------------------
// La pantalla
// ---------------------------------------------------------------------

it('deja al administrativo mandar un ítem a revisión desde el listado', function (): void {
    $item = ItemInventario::factory()->create(['nombre' => 'Sonda vesical']);

    Livewire::actingAs($this->administrativo)
        ->test(ListadoInventario::class)
        ->call('pedirCambioDeEstado', $item->id, EstadoItemInventario::EnRevision->value)
        ->set('motivo', 'El balón no infla.')
        ->call('confirmarCambioDeEstado')
        ->assertHasNoErrors();

    expect($item->fresh()->estado)->toBe(EstadoItemInventario::EnRevision);
});

it('exige el motivo en el formulario antes de guardar', function (): void {
    $item = ItemInventario::factory()->create();

    Livewire::actingAs($this->administrativo)
        ->test(ListadoInventario::class)
        ->call('pedirCambioDeEstado', $item->id, EstadoItemInventario::EnRevision->value)
        ->set('motivo', '')
        ->call('confirmarCambioDeEstado')
        ->assertHasErrors('motivo');

    expect($item->fresh()->estado)->toBe(EstadoItemInventario::Operativo);
});

it('no enseña al administrativo el control de dar de baja', function (): void {
    ItemInventario::factory()->defectuoso()->create();

    Livewire::actingAs($this->administrativo)
        ->test(ListadoInventario::class)
        ->assertDontSee('Dar de baja');
});

it('enseña el historial con su motivo y su responsable', function (): void {
    $item = ItemInventario::factory()->create(['nombre' => 'Máscara de no reinhalación']);
    $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::EnRevision, 'El filtro está dañado.');

    Livewire::actingAs($this->administrativo)
        ->test(ListadoInventario::class)
        ->assertSee('El filtro está dañado.')
        ->assertSee($this->administrativo->nombre);
});
