<?php

declare(strict_types=1);

use App\Enums\EstadoItemInventario;
use App\Enums\NivelFidelidad;
use App\Enums\Rol;
use App\Enums\TipoItemInventario;
use App\Exceptions\InventarioInvalido;
use App\Models\ItemInventario;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\DatosItemInventario;
use App\Services\InventarioService;
use Database\Seeders\RolSeeder;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->servicio = app(InventarioService::class);
    $this->admin = User::factory()->admin()->create();
    $this->administrativo = User::factory()->administrativo()->create();
    $this->coordinadora = User::factory()->coordinador()->create();
});

/** Datos de un simulador, con nivel de fidelidad opcional. */
function datosDeSimulador(?NivelFidelidad $nivel = null, string $nombre = 'Maniquí de parto'): DatosItemInventario
{
    return new DatosItemInventario(
        nombre: $nombre,
        tipo: TipoItemInventario::Simulador,
        cantidadTotal: 3,
        nivelFidelidad: $nivel,
    );
}

/** Datos de un equipo clínico, que nunca lleva fidelidad. */
function datosDeEquipo(?NivelFidelidad $nivel = null): DatosItemInventario
{
    return new DatosItemInventario(
        nombre: 'Monitor de signos vitales',
        tipo: TipoItemInventario::EquipoClinico,
        cantidadTotal: 6,
        nivelFidelidad: $nivel,
    );
}

// ---------------------------------------------------------------------
// Nivel de fidelidad: restricción de campo, no de recurso
// ---------------------------------------------------------------------

it('deja al ADMIN registrar el nivel de fidelidad', function (): void {
    $item = $this->servicio->crear($this->admin, datosDeSimulador(NivelFidelidad::Alta));

    expect($item->nivel_fidelidad)->toBe(NivelFidelidad::Alta);
});

it('no deja a un administrativo ni a un coordinador fijar el nivel de fidelidad', function (string $rol): void {
    $actor = $rol === 'administrativo' ? $this->administrativo : $this->coordinadora;

    expect(fn () => $this->servicio->crear($actor, datosDeSimulador(NivelFidelidad::Alta)))
        ->toThrow(InventarioInvalido::class, 'solo lo registra el ADMIN');
})->with(['administrativo', 'coordinador']);

it('deja a un administrativo editar el resto de campos del mismo ítem', function (): void {
    // La restricción es de campo, no de recurso.
    $item = $this->servicio->crear($this->admin, datosDeSimulador(NivelFidelidad::Media));

    $this->servicio->actualizar($this->administrativo, $item, new DatosItemInventario(
        nombre: 'Maniquí de parto renovado',
        tipo: TipoItemInventario::Simulador,
        cantidadTotal: 5,
        descripcion: 'Repuesto en 2026',
        nivelFidelidad: NivelFidelidad::Media, // el valor que ya tenía
    ));

    $item = $item->fresh();
    expect($item->nombre)->toBe('Maniquí de parto renovado')
        ->and($item->cantidad_total)->toBe(5)
        ->and($item->descripcion)->toBe('Repuesto en 2026')
        ->and($item->nivel_fidelidad)->toBe(NivelFidelidad::Media);
});

it('no deja a un administrativo cambiar el nivel de fidelidad al actualizar', function (): void {
    $item = $this->servicio->crear($this->admin, datosDeSimulador(NivelFidelidad::Baja));

    expect(fn () => $this->servicio->actualizar($this->administrativo, $item, new DatosItemInventario(
        nombre: 'Maniquí de parto',
        tipo: TipoItemInventario::Simulador,
        cantidadTotal: 3,
        nivelFidelidad: NivelFidelidad::Alta,
    )))->toThrow(InventarioInvalido::class, 'solo lo registra el ADMIN');

    expect($item->fresh()->nivel_fidelidad)->toBe(NivelFidelidad::Baja);
});

it('no deja a un administrativo borrar el nivel de fidelidad poniéndolo a nulo', function (): void {
    $item = $this->servicio->crear($this->admin, datosDeSimulador(NivelFidelidad::Alta));

    expect(fn () => $this->servicio->actualizar($this->administrativo, $item, datosDeSimulador(null)))
        ->toThrow(InventarioInvalido::class, 'solo lo registra el ADMIN');
});

it('ignora el nivel de fidelidad en cualquier asignación masiva', function (): void {
    // Garantía estructural: la columna está fuera de fillable, así que un
    // formulario que la envíe no puede colarla por create() ni update().
    $item = ItemInventario::create([
        'nombre' => 'Torso de RCP',
        'tipo' => TipoItemInventario::Simulador,
        'nivel_fidelidad' => NivelFidelidad::Alta,
        'cantidad_total' => 2,
    ]);

    expect($item->nivel_fidelidad)->toBeNull();

    $item->update(['nombre' => 'Torso', 'nivel_fidelidad' => NivelFidelidad::Media]);

    expect($item->fresh()->nombre)->toBe('Torso')
        ->and($item->fresh()->nivel_fidelidad)->toBeNull();
});

it('no acepta nivel de fidelidad en un ítem que no es simulador', function (): void {
    expect(fn () => $this->servicio->crear($this->admin, datosDeEquipo(NivelFidelidad::Alta)))
        ->toThrow(InventarioInvalido::class, 'no lleva nivel de fidelidad');
});

it('crea sin problema equipos sin nivel de fidelidad', function (): void {
    $item = $this->servicio->crear($this->administrativo, datosDeEquipo());

    expect($item->nivel_fidelidad)->toBeNull()
        ->and($item->tipo)->toBe(TipoItemInventario::EquipoClinico);
});

// ---------------------------------------------------------------------
// Acceso
// ---------------------------------------------------------------------

it('no deja a un docente ni a un estudiante acercarse al inventario', function (Rol $rol): void {
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);
    $item = ItemInventario::factory()->create();

    expect($usuario->can('viewAny', ItemInventario::class))->toBeFalse()
        ->and($usuario->can('view', $item))->toBeFalse()
        ->and($usuario->can('create', ItemInventario::class))->toBeFalse()
        ->and($usuario->can('update', $item))->toBeFalse()
        ->and($usuario->can('editarNivelFidelidad', $item))->toBeFalse();
})->with([Rol::Docente, Rol::Estudiante]);

it('deja gestionar el inventario a administrativo, coordinador y ADMIN', function (Rol $rol): void {
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);
    $item = ItemInventario::factory()->create();

    expect($usuario->can('viewAny', ItemInventario::class))->toBeTrue()
        ->and($usuario->can('update', $item))->toBeTrue()
        ->and($usuario->can('darDeBaja', $item))->toBeTrue();
})->with([Rol::Administrativo, Rol::Coordinador, Rol::Admin]);

it('reserva el nivel de fidelidad al ADMIN en la Policy', function (): void {
    $item = ItemInventario::factory()->simulador()->create();

    expect($this->admin->can('editarNivelFidelidad', $item))->toBeTrue()
        ->and($this->administrativo->can('editarNivelFidelidad', $item))->toBeFalse()
        ->and($this->coordinadora->can('editarNivelFidelidad', $item))->toBeFalse();
});

// ---------------------------------------------------------------------
// Baja lógica
// ---------------------------------------------------------------------

it('da de baja sin borrar el registro ni romper el histórico', function (): void {
    $item = ItemInventario::factory()->create();
    $solicitud = Solicitud::factory()->create();
    $solicitud->items()->attach($item->id, ['cantidad' => 2]);

    $this->servicio->darDeBaja($item);

    expect($item->fresh()->estado)->toBe(EstadoItemInventario::Baja)
        ->and($item->fresh()->activo)->toBeFalse()
        ->and(ItemInventario::find($item->id))->not->toBeNull()
        ->and($solicitud->fresh()->items->firstWhere('id', $item->id)->pivot->cantidad)->toBe(2);
});

// ---------------------------------------------------------------------
// Listado filtrable
// ---------------------------------------------------------------------

it('filtra el listado por tipo, estado y nivel de fidelidad', function (): void {
    ItemInventario::factory()->simulador(NivelFidelidad::Alta)->create();
    ItemInventario::factory()->simulador(NivelFidelidad::Baja)->create();
    ItemInventario::factory()->simulador(NivelFidelidad::Alta)->enMantenimiento()->create();
    ItemInventario::factory()->equipoBasico()->create();

    expect($this->servicio->listar())->toHaveCount(4)
        ->and($this->servicio->listar(tipo: TipoItemInventario::Simulador))->toHaveCount(3)
        ->and($this->servicio->listar(tipo: TipoItemInventario::EquipoBasico))->toHaveCount(1)
        ->and($this->servicio->listar(estado: EstadoItemInventario::Mantenimiento))->toHaveCount(1)
        ->and($this->servicio->listar(nivelFidelidad: NivelFidelidad::Alta))->toHaveCount(2)
        ->and($this->servicio->listar(
            tipo: TipoItemInventario::Simulador,
            estado: EstadoItemInventario::Disponible,
            nivelFidelidad: NivelFidelidad::Alta,
        ))->toHaveCount(1);
});
