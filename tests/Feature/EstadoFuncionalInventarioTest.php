<?php

declare(strict_types=1);

use App\Enums\EstadoItemInventario;
use App\Enums\Rol;
use App\Enums\TipoItemInventario;
use App\Exceptions\InventarioInvalido;
use App\Livewire\Inventario\ListadoInventario;
use App\Models\CambioEstadoItem;
use App\Models\ItemInventario;
use App\Models\User;
use App\Services\DatosItemInventario;
use App\Services\InventarioService;
use Database\Seeders\RolSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseCount;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->servicio = app(InventarioService::class);
    $this->administrativo = User::factory()->administrativo()->create();
    $this->coordinadora = User::factory()->coordinador()->create();
});

/** Un ítem con ocho unidades, todas operativas, creado por el Service. */
function ochoSondas(User $actor): ItemInventario
{
    return app(InventarioService::class)->crear($actor, new DatosItemInventario(
        nombre: 'Sonda vesical',
        tipo: TipoItemInventario::EquipoClinico,
        cantidadTotal: 8,
    ));
}

/** Reconstruye los contadores replicando el historial de un ítem. */
function contadoresSegunElHistorial(ItemInventario $item): array
{
    $saldos = ['cantidad_operativa' => 0, 'cantidad_en_revision' => 0, 'cantidad_defectuosa' => 0];

    foreach ($item->cambiosDeEstado()->get()->sortBy('id') as $cambio) {
        $entra = $cambio->estado_nuevo->columnaDeCantidad();
        $sale = $cambio->estado_anterior?->columnaDeCantidad();

        if ($entra !== null) {
            $saldos[$entra] += $cambio->cantidad;
        }

        if ($sale !== null) {
            $saldos[$sale] -= $cambio->cantidad;
        }
    }

    return $saldos + ['cantidad_total' => array_sum($saldos)];
}

// ---------------------------------------------------------------------
// El estado es de las unidades, no del ítem
// ---------------------------------------------------------------------

it('manda unas pocas unidades a revisión y deja el resto disponible', function (): void {
    // El caso que motivó el requerimiento: de ocho sondas fallan dos.
    $item = ochoSondas($this->administrativo);

    $item = $this->servicio->cambiarEstado(
        $this->administrativo,
        $item,
        EstadoItemInventario::Operativo,
        EstadoItemInventario::EnRevision,
        2,
        'A dos sondas no les infla el balón.',
    );

    expect($item->cantidad_operativa)->toBe(6)
        ->and($item->cantidad_en_revision)->toBe(2)
        ->and($item->cantidad_total)->toBe(8)
        ->and($this->servicio->disponibilidadEnFranja($item, '2026-10-05', '07:00:00', '09:00:00'))->toBe(6);
});

it('crea todo ítem con sus unidades operativas', function (): void {
    $item = ochoSondas($this->administrativo);

    expect($item->cantidad_operativa)->toBe(8)
        ->and($item->cantidad_total)->toBe(8)
        ->and($item->cantidad_en_revision)->toBe(0);
});

it('confirma como defectuosas solo las que estaban en revisión', function (): void {
    $item = ochoSondas($this->administrativo);
    $item = $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::Operativo, EstadoItemInventario::EnRevision, 3, 'Fallan tres.');

    $item = $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::EnRevision, EstadoItemInventario::Defectuoso, 2, 'Dos sin arreglo.');

    expect($item->cantidad_operativa)->toBe(5)
        ->and($item->cantidad_en_revision)->toBe(1)
        ->and($item->cantidad_defectuosa)->toBe(2);
});

it('devuelve a operativas las que resultaron falsa alarma', function (): void {
    $item = ochoSondas($this->administrativo);
    $item = $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::Operativo, EstadoItemInventario::EnRevision, 3, 'Fallan tres.');

    $item = $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::EnRevision, EstadoItemInventario::Operativo, 3, 'Era el conector, ya están bien.');

    expect($item->cantidad_operativa)->toBe(8)
        ->and($item->cantidad_en_revision)->toBe(0);
});

// ---------------------------------------------------------------------
// La invariante
// ---------------------------------------------------------------------

it('no deja mover más unidades de las que hay en el estado de origen', function (): void {
    $item = ochoSondas($this->administrativo);
    $item = $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::Operativo, EstadoItemInventario::EnRevision, 2, 'Fallan dos.');

    expect(fn () => $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::EnRevision, EstadoItemInventario::Defectuoso, 3, 'Tres.'))
        ->toThrow(InventarioInvalido::class, 'Solo hay 2 unidad(es) en "En revisión" y se pidió mover 3.');

    expect($item->fresh()->cantidad_en_revision)->toBe(2);
});

it('no deja mover cero ni una cantidad negativa', function (int $cantidad): void {
    $item = ochoSondas($this->administrativo);

    expect(fn () => $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::Operativo, EstadoItemInventario::EnRevision, $cantidad, 'Lo que sea.'))
        ->toThrow(InventarioInvalido::class, 'al menos una unidad');
})->with(['cero' => 0, 'negativa' => -3]);

it('mantiene el total igual a la suma de los contadores en todo momento', function (): void {
    $item = ochoSondas($this->administrativo);

    $item = $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::Operativo, EstadoItemInventario::EnRevision, 4, 'Cuatro raras.');
    $item = $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::EnRevision, EstadoItemInventario::Defectuoso, 3, 'Tres sin arreglo.');
    $item = $this->servicio->darDeBaja($this->coordinadora, $item, 2, 'Sin repuesto.');

    expect($item->cantidad_total)->toBe(
        $item->cantidad_operativa + $item->cantidad_en_revision + $item->cantidad_defectuosa,
    );
});

it('reconstruye los contadores replicando el historial', function (): void {
    // Es la garantía que daría derivarlo todo del historial, sin pagar una
    // agregación en cada cálculo de disponibilidad.
    $item = ochoSondas($this->administrativo);

    $item = $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::Operativo, EstadoItemInventario::EnRevision, 5, 'Cinco a revisar.');
    $item = $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::EnRevision, EstadoItemInventario::Defectuoso, 4, 'Cuatro malas.');
    $item = $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::EnRevision, EstadoItemInventario::Operativo, 1, 'Una era falsa alarma.');
    $item = $this->servicio->darDeBaja($this->coordinadora, $item, 3, 'Tres al contenedor.');
    $item = $this->servicio->reponerUnidades($this->administrativo, $item, 2, 'Llegaron dos de la compra.');
    $item = $this->servicio->retirarUnidades($this->administrativo, $item, 1, 'Una se perdió en el traslado.');

    $item->refresh();

    expect(contadoresSegunElHistorial($item))->toBe([
        'cantidad_operativa' => $item->cantidad_operativa,
        'cantidad_en_revision' => $item->cantidad_en_revision,
        'cantidad_defectuosa' => $item->cantidad_defectuosa,
        'cantidad_total' => $item->cantidad_total,
    ]);
});

it('la base rechaza unos contadores que no sumen el total', function (): void {
    // Última red, por debajo del Service: ni tinker, ni un seeder, ni una
    // migración futura descuidada pueden dejar el ítem descuadrado.
    $item = ochoSondas($this->administrativo);

    // No se comprueba nada después del fallo: PostgreSQL aborta la
    // transacción del test y cualquier consulta posterior reventaría por eso
    // y no por lo que se quiere probar.
    expect(fn () => DB::table('items_inventario')
        ->where('id', $item->id)
        ->update(['cantidad_operativa' => 3]))
        ->toThrow(QueryException::class, 'items_inventario_cantidades_cuadran');
});

it('no deja que una asignación masiva toque las cantidades', function (): void {
    $item = ochoSondas($this->administrativo);

    $item->update(['cantidad_total' => 99, 'cantidad_operativa' => 99]);

    expect($item->fresh()->cantidad_total)->toBe(8)
        ->and($item->fresh()->cantidad_operativa)->toBe(8);
});

// ---------------------------------------------------------------------
// Entradas y salidas del inventario
// ---------------------------------------------------------------------

it('da de baja unas unidades y las descuenta del total para siempre', function (): void {
    $item = ochoSondas($this->administrativo);
    $item = $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::Operativo, EstadoItemInventario::EnRevision, 3, 'Tres raras.');
    $item = $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::EnRevision, EstadoItemInventario::Defectuoso, 3, 'Tres malas.');

    $item = $this->servicio->darDeBaja($this->coordinadora, $item, 2, 'Sin repuesto.');

    expect($item->cantidad_total)->toBe(6)
        ->and($item->cantidad_defectuosa)->toBe(1)
        ->and($item->cantidad_operativa)->toBe(5)
        ->and($item->activo)->toBeTrue();
});

it('saca el ítem del catálogo cuando se va la última unidad', function (): void {
    $item = app(InventarioService::class)->crear($this->administrativo, new DatosItemInventario(
        nombre: 'Simulador de auscultación',
        tipo: TipoItemInventario::Simulador,
        cantidadTotal: 1,
    ));
    $item = $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::Operativo, EstadoItemInventario::EnRevision, 1, 'No suena.');
    $item = $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::EnRevision, EstadoItemInventario::Defectuoso, 1, 'La membrana está rota.');

    $item = $this->servicio->darDeBaja($this->coordinadora, $item, 1, 'No se consigue repuesto.');

    expect($item->cantidad_total)->toBe(0)
        ->and($item->activo)->toBeFalse()
        ->and(ItemInventario::find($item->id))->not->toBeNull();
});

it('registra la salida de unidades que no son una avería', function (): void {
    // Gasas gastadas en prácticas. No se modela el consumo como concepto,
    // pero bajar el total nunca es silencioso.
    $item = ochoSondas($this->administrativo);

    $item = $this->servicio->retirarUnidades($this->administrativo, $item, 3, 'Consumo de las prácticas de la semana.');

    $cambio = $item->cambiosDeEstado()->first();

    expect($item->cantidad_total)->toBe(5)
        ->and($item->cantidad_operativa)->toBe(5)
        ->and($cambio->cantidad)->toBe(3)
        ->and($cambio->estado_anterior)->toBe(EstadoItemInventario::Operativo)
        ->and($cambio->estado_nuevo)->toBe(EstadoItemInventario::DadoDeBaja)
        ->and($cambio->motivo)->toBe('Consumo de las prácticas de la semana.')
        ->and($cambio->registrado_por)->toBe($this->administrativo->id);
});

it('distingue en el historial la baja por avería de la salida sin avería', function (): void {
    $item = ochoSondas($this->administrativo);
    $item = $this->servicio->retirarUnidades($this->administrativo, $item, 1, 'Se perdió.');
    $item = $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::Operativo, EstadoItemInventario::EnRevision, 1, 'Rara.');
    $item = $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::EnRevision, EstadoItemInventario::Defectuoso, 1, 'Mala.');
    $this->servicio->darDeBaja($this->coordinadora, $item, 1, 'Al contenedor.');

    $salidas = $item->cambiosDeEstado()->get()->filter->esSalida();

    expect($salidas)->toHaveCount(2)
        ->and($salidas->pluck('estado_anterior')->all())
        ->toEqualCanonicalizing([EstadoItemInventario::Operativo, EstadoItemInventario::Defectuoso]);
});

it('repone unidades y las deja operativas', function (): void {
    $item = ochoSondas($this->administrativo);

    $item = $this->servicio->reponerUnidades($this->administrativo, $item, 4, 'Llegó la compra del semestre.');

    expect($item->cantidad_total)->toBe(12)
        ->and($item->cantidad_operativa)->toBe(12);
});

it('no deja reponer ni retirar sin motivo', function (string $metodo): void {
    $item = ochoSondas($this->administrativo);

    expect(fn () => $this->servicio->$metodo($this->administrativo, $item, 1, '   '))
        ->toThrow(InventarioInvalido::class, 'necesita un motivo');
})->with(['reponerUnidades', 'retirarUnidades']);

// ---------------------------------------------------------------------
// El flujo del RF66 sigue vigente
// ---------------------------------------------------------------------

it('no deja saltarse la revisión para marcar un defecto', function (): void {
    $item = ochoSondas($this->administrativo);

    expect(fn () => $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::Operativo, EstadoItemInventario::Defectuoso, 1, 'Está rota.'))
        ->toThrow(InventarioInvalido::class);
});

it('no deja dar de baja unidades operativas por la puerta del flujo', function (): void {
    // Salen por retirarUnidades(), que es otra cosa y se registra distinto.
    // Lo intenta la coordinadora, que sí tiene el permiso de baja: así lo
    // que corta es el flujo y no la autorización.
    $item = ochoSondas($this->administrativo);

    expect(fn () => $this->servicio->cambiarEstado($this->coordinadora, $item, EstadoItemInventario::Operativo, EstadoItemInventario::DadoDeBaja, 1, 'Fuera.'))
        ->toThrow(InventarioInvalido::class);
});

it('no deja mover unidades desde la baja', function (): void {
    $item = ochoSondas($this->administrativo);

    expect(fn () => $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::DadoDeBaja, EstadoItemInventario::Operativo, 1, 'Volvieron.'))
        ->toThrow(InventarioInvalido::class, 'no vuelve a cambiar de estado');
});

it('no deja cambiar el estado sin motivo', function (string $motivo): void {
    $item = ochoSondas($this->administrativo);

    expect(fn () => $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::Operativo, EstadoItemInventario::EnRevision, 1, $motivo))
        ->toThrow(InventarioInvalido::class, 'necesita un motivo');

    expect($item->fresh()->cantidad_operativa)->toBe(8);
})->with(['vacío' => '', 'solo espacios' => '   ']);

// ---------------------------------------------------------------------
// Piezas únicas
// ---------------------------------------------------------------------

it('trata una pieza única como un ítem de una sola unidad', function (): void {
    // Sin caso especial: mover su única unidad la deja sin disponibilidad,
    // que es el comportamiento de antes como caso particular.
    $item = app(InventarioService::class)->crear($this->administrativo, new DatosItemInventario(
        nombre: 'Maniquí de parto',
        tipo: TipoItemInventario::Simulador,
        cantidadTotal: 1,
    ));

    $item = $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::Operativo, EstadoItemInventario::EnRevision, 1, 'No dilata.');

    expect($item->cantidad_operativa)->toBe(0)
        ->and($this->servicio->disponibilidadEnFranja($item, '2026-10-05', '07:00:00', '09:00:00'))->toBe(0);
});

// ---------------------------------------------------------------------
// Permisos (RF66.4)
// ---------------------------------------------------------------------

it('deja mover unidades a quien gestiona el inventario', function (string $quien): void {
    $item = ochoSondas($this->administrativo);

    $item = $this->servicio->cambiarEstado($this->$quien, $item, EstadoItemInventario::Operativo, EstadoItemInventario::EnRevision, 1, 'Suena raro.');

    expect($item->cantidad_en_revision)->toBe(1);
})->with(['administrativo', 'coordinadora']);

it('no deja a un docente ni a un estudiante mover unidades', function (Rol $rol): void {
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);
    $item = ochoSondas($this->administrativo);
    $cambiosDelAlta = CambioEstadoItem::count();

    expect(fn () => $this->servicio->cambiarEstado($usuario->fresh(), $item, EstadoItemInventario::Operativo, EstadoItemInventario::EnRevision, 1, 'Yo qué sé.'))
        ->toThrow(AuthorizationException::class);

    expect($item->fresh()->cantidad_operativa)->toBe(8)
        ->and(CambioEstadoItem::count())->toBe($cambiosDelAlta);
})->with([Rol::Docente, Rol::Estudiante]);

it('no deja al administrativo dar de baja, que es lo único reservado', function (): void {
    $item = ochoSondas($this->administrativo);
    $item = $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::Operativo, EstadoItemInventario::EnRevision, 1, 'Rara.');
    $item = $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::EnRevision, EstadoItemInventario::Defectuoso, 1, 'Mala.');

    expect(fn () => $this->servicio->darDeBaja($this->administrativo, $item, 1, 'Al contenedor.'))
        ->toThrow(AuthorizationException::class);

    expect($item->fresh()->cantidad_defectuosa)->toBe(1);
});

// ---------------------------------------------------------------------
// La pantalla
// ---------------------------------------------------------------------

it('deja mandar unas unidades a revisión desde el listado', function (): void {
    $item = ItemInventario::factory()->create(['nombre' => 'Sonda vesical', 'cantidad_total' => 8]);

    Livewire::actingAs($this->administrativo)
        ->test(ListadoInventario::class)
        ->call('pedirCambioDeEstado', $item->id, EstadoItemInventario::Operativo->value, EstadoItemInventario::EnRevision->value)
        ->set('cantidad', 2)
        ->set('motivo', 'A dos no les infla el balón.')
        ->call('confirmarCambioDeEstado')
        ->assertHasNoErrors();

    expect($item->fresh()->cantidad_operativa)->toBe(6)
        ->and($item->fresh()->cantidad_en_revision)->toBe(2);
});

it('exige cantidad y motivo en el formulario', function (): void {
    $item = ItemInventario::factory()->create(['cantidad_total' => 8]);

    Livewire::actingAs($this->administrativo)
        ->test(ListadoInventario::class)
        ->call('pedirCambioDeEstado', $item->id, EstadoItemInventario::Operativo->value, EstadoItemInventario::EnRevision->value)
        ->set('cantidad', 0)
        ->set('motivo', '')
        ->call('confirmarCambioDeEstado')
        ->assertHasErrors(['cantidad', 'motivo']);

    expect($item->fresh()->cantidad_operativa)->toBe(8);
});

it('avisa en pantalla cuando se piden más unidades de las que hay', function (): void {
    $item = ItemInventario::factory()->enRevision(2)->create(['cantidad_total' => 8]);

    Livewire::actingAs($this->administrativo)
        ->test(ListadoInventario::class)
        ->call('pedirCambioDeEstado', $item->id, EstadoItemInventario::EnRevision->value, EstadoItemInventario::Defectuoso->value)
        ->set('cantidad', 5)
        ->set('motivo', 'Cinco malas.')
        ->call('confirmarCambioDeEstado')
        ->assertSee('Solo hay 2 unidad(es)');

    expect($item->fresh()->cantidad_en_revision)->toBe(2);
});

it('enseña el desglose por estado en vez de una etiqueta única', function (): void {
    ItemInventario::factory()->enRevision(2)->create(['nombre' => 'Sonda vesical', 'cantidad_total' => 8]);

    Livewire::actingAs($this->administrativo)
        ->test(ListadoInventario::class)
        ->assertSee('6 operativo')
        ->assertSee('2 en revisión');
});

it('filtra los ítems con unidades no operativas', function (): void {
    ItemInventario::factory()->create(['nombre' => 'Todo bien', 'cantidad_total' => 4]);
    ItemInventario::factory()->enRevision(1)->create(['nombre' => 'Algo raro', 'cantidad_total' => 4]);

    Livewire::actingAs($this->administrativo)
        ->test(ListadoInventario::class)
        ->set('estado', ListadoInventario::NO_OPERATIVAS)
        ->assertSee('Algo raro')
        ->assertDontSee('Todo bien');
});

it('enseña el historial con cuántas unidades, motivo y responsable', function (): void {
    $item = ochoSondas($this->administrativo);
    $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::Operativo, EstadoItemInventario::EnRevision, 2, 'A dos no les infla el balón.');

    Livewire::actingAs($this->administrativo)
        ->test(ListadoInventario::class)
        ->assertSee('A dos no les infla el balón.')
        ->assertSee($this->administrativo->nombre)
        ->assertSee('2 ×');
});

it('anota el alta del ítem en el historial', function (): void {
    ochoSondas($this->administrativo);

    assertDatabaseCount('cambios_estado_item', 1);

    $alta = CambioEstadoItem::first();

    expect($alta->esEntrada())->toBeTrue()
        ->and($alta->cantidad)->toBe(8)
        ->and($alta->estado_nuevo)->toBe(EstadoItemInventario::Operativo);
});
