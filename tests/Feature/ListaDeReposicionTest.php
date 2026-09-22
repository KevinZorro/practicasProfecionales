<?php

declare(strict_types=1);

use App\Enums\EstadoItemInventario;
use App\Enums\MotivoReposicion;
use App\Enums\Reporte;
use App\Enums\Rol;
use App\Enums\TipoItemInventario;
use App\Exceptions\ReposicionInvalida;
use App\Livewire\Reposicion\ListaDeReposicion as Pantalla;
use App\Models\ItemInventario;
use App\Models\LineaDeReposicion;
use App\Models\ListaDeReposicion;
use App\Models\NecesidadDeReposicion;
use App\Models\User;
use App\Services\DatosItemInventario;
use App\Services\DatosNecesidad;
use App\Services\FiltroReporte;
use App\Services\GeneradorDeReportes;
use App\Services\InventarioService;
use App\Services\ReposicionService;
use Database\Seeders\RolSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->reposicion = app(ReposicionService::class);
    $this->inventario = app(InventarioService::class);
    $this->administrativo = User::factory()->administrativo()->create();
    $this->coordinadora = User::factory()->coordinador()->create();
});

/** Un ítem con las unidades pedidas, creado por el Service. */
function itemCon(string $nombre, int $unidades, User $actor): ItemInventario
{
    return app(InventarioService::class)->crear($actor, new DatosItemInventario(
        nombre: $nombre,
        tipo: TipoItemInventario::EquipoBasico,
        cantidadTotal: $unidades,
    ));
}

/** Lleva N unidades hasta defectuosas y las da de baja. */
function darDeBajaPorAveria(ItemInventario $item, int $cantidad, User $coordinadora, User $administrativo): void
{
    $servicio = app(InventarioService::class);
    $item = $servicio->cambiarEstado($administrativo, $item, EstadoItemInventario::Operativo, EstadoItemInventario::EnRevision, $cantidad, 'Fallan.');
    $item = $servicio->cambiarEstado($administrativo, $item, EstadoItemInventario::EnRevision, EstadoItemInventario::Defectuoso, $cantidad, 'Sin arreglo.');
    $servicio->darDeBaja($coordinadora, $item, $cantidad, 'Al contenedor.');
}

// ---------------------------------------------------------------------
// Agregación por motivo
// ---------------------------------------------------------------------

it('separa lo gastado de lo averiado', function (): void {
    $gasas = itemCon('Gasas estériles', 100, $this->administrativo);
    $sondas = itemCon('Sonda vesical', 10, $this->administrativo);

    $this->inventario->retirarUnidades($this->administrativo, $gasas, 40, 'Consumo de las prácticas.');
    darDeBajaPorAveria($sondas, 3, $this->coordinadora, $this->administrativo);

    $lista = $this->reposicion->previsualizar('2020-01-01', '2030-12-31');

    expect($lista->firstWhere('descripcion', 'Gasas estériles'))
        ->toMatchArray(['motivo' => MotivoReposicion::Consumo, 'cantidad' => 40])
        ->and($lista->firstWhere('descripcion', 'Sonda vesical'))
        ->toMatchArray(['motivo' => MotivoReposicion::Averia, 'cantidad' => 3]);
});

it('suma varias salidas del mismo ítem y motivo', function (): void {
    $gasas = itemCon('Gasas estériles', 100, $this->administrativo);

    $this->inventario->retirarUnidades($this->administrativo, $gasas, 10, 'Prácticas de agosto.');
    $this->inventario->retirarUnidades($this->administrativo, $gasas, 25, 'Prácticas de septiembre.');

    expect($this->reposicion->previsualizar('2020-01-01', '2030-12-31')->firstWhere('descripcion', 'Gasas estériles'))
        ->toMatchArray(['cantidad' => 35]);
});

it('lleva el mismo ítem en dos líneas cuando se gastó y además se averió', function (): void {
    $item = itemCon('Tensiómetro', 20, $this->administrativo);

    $this->inventario->retirarUnidades($this->administrativo, $item, 5, 'Se perdieron en el traslado.');
    darDeBajaPorAveria($item, 2, $this->coordinadora, $this->administrativo);

    $lineas = $this->reposicion->previsualizar('2020-01-01', '2030-12-31')
        ->where('descripcion', 'Tensiómetro');

    expect($lineas)->toHaveCount(2)
        ->and($lineas->pluck('motivo')->all())
        ->toEqualCanonicalizing([MotivoReposicion::Consumo, MotivoReposicion::Averia]);
});

it('no cuenta el alta del ítem como algo que haya que pedir', function (): void {
    // El alta es una entrada, no una salida.
    itemCon('Jeringas 10 ml', 300, $this->administrativo);

    expect($this->reposicion->previsualizar('2020-01-01', '2030-12-31'))->toBeEmpty();
});

// ---------------------------------------------------------------------
// Rango de fechas
// ---------------------------------------------------------------------

it('deja fuera los movimientos anteriores al rango', function (): void {
    $gasas = itemCon('Gasas estériles', 100, $this->administrativo);

    $this->travelTo('2026-03-10');
    $this->inventario->retirarUnidades($this->administrativo, $gasas, 30, 'Prácticas de marzo.');
    $this->travelBack();

    expect($this->reposicion->previsualizar('2026-07-01', '2026-12-15'))->toBeEmpty();
});

it('deja fuera los movimientos posteriores al rango', function (): void {
    $gasas = itemCon('Gasas estériles', 100, $this->administrativo);

    $this->travelTo('2027-02-10');
    $this->inventario->retirarUnidades($this->administrativo, $gasas, 30, 'Prácticas del otro semestre.');
    $this->travelBack();

    expect($this->reposicion->previsualizar('2026-07-01', '2026-12-15'))->toBeEmpty();
});

it('incluye los movimientos del primer y del último día del rango', function (string $dia): void {
    $gasas = itemCon('Gasas estériles', 100, $this->administrativo);

    $this->travelTo($dia.' 10:00:00');
    $this->inventario->retirarUnidades($this->administrativo, $gasas, 7, 'Prácticas del día.');
    $this->travelBack();

    expect($this->reposicion->previsualizar('2026-07-01', '2026-12-15')->firstWhere('descripcion', 'Gasas estériles'))
        ->toMatchArray(['cantidad' => 7]);
})->with(['primero' => '2026-07-01', 'último' => '2026-12-15']);

it('deja fuera las necesidades anotadas fuera del rango', function (): void {
    NecesidadDeReposicion::factory()->enLaFecha('2026-03-01')->create();

    expect($this->reposicion->previsualizar('2026-07-01', '2026-12-15'))->toBeEmpty();
});

// ---------------------------------------------------------------------
// Necesidades anotadas a mano
// ---------------------------------------------------------------------

it('registra una necesidad de algo que no está en el catálogo', function (): void {
    // El ejemplo del cliente: una pila que el laboratorio nunca tuvo.
    $necesidad = $this->reposicion->registrarNecesidad($this->administrativo, new DatosNecesidad(
        cantidad: 2,
        justificacion: 'Se pidió para la práctica de reanimación y no había.',
        descripcion: 'Pila CR2032 para el control del desfibrilador',
    ));

    expect($necesidad->esDeFueraDelCatalogo())->toBeTrue()
        ->and($necesidad->queSePide())->toBe('Pila CR2032 para el control del desfibrilador')
        ->and($necesidad->registrada_por)->toBe($this->administrativo->id);
});

it('no crea un ítem de inventario para lo que no existe', function (): void {
    // Un ítem con cero unidades aparecería en la disponibilidad y en el
    // formulario del docente como si el laboratorio lo tuviera.
    $antes = ItemInventario::count();

    $this->reposicion->registrarNecesidad($this->administrativo, new DatosNecesidad(
        cantidad: 2,
        justificacion: 'No había.',
        descripcion: 'Pila CR2032',
    ));

    expect(ItemInventario::count())->toBe($antes);
});

it('registra que hace falta más de algo que sí está en el catálogo', function (): void {
    $gasas = itemCon('Gasas estériles', 100, $this->administrativo);

    $necesidad = $this->reposicion->registrarNecesidad($this->administrativo, new DatosNecesidad(
        cantidad: 200,
        justificacion: 'Se acaban antes de fin de semestre.',
        itemInventarioId: $gasas->id,
    ));

    expect($necesidad->esDeFueraDelCatalogo())->toBeFalse()
        ->and($necesidad->queSePide())->toBe('Gasas estériles');
});

it('exige saber qué se pide', function (): void {
    expect(fn () => $this->reposicion->registrarNecesidad($this->administrativo, new DatosNecesidad(
        cantidad: 1,
        justificacion: 'Algo hacía falta.',
    )))->toThrow(ReposicionInvalida::class, 'Elige un ítem del inventario o escribe qué es');
});

it('exige pedir al menos una unidad', function (): void {
    expect(fn () => $this->reposicion->registrarNecesidad($this->administrativo, new DatosNecesidad(
        cantidad: 0,
        justificacion: 'Ninguna.',
        descripcion: 'Pila CR2032',
    )))->toThrow(ReposicionInvalida::class, 'al menos una unidad');
});

it('la base rechaza una necesidad que no dice qué pide', function (): void {
    // No se comprueba nada después: PostgreSQL aborta la transacción del test.
    expect(fn () => DB::table('necesidades_reposicion')->insert([
        'item_inventario_id' => null,
        'descripcion' => null,
        'cantidad' => 1,
        'justificacion' => 'Nada.',
        'fecha' => '2026-09-15',
        'registrada_por' => $this->administrativo->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class, 'necesidades_reposicion_saben_que_piden');
});

it('junta las necesidades de lo mismo en una sola línea', function (): void {
    NecesidadDeReposicion::factory()->count(2)->enLaFecha('2026-09-15')->create(['cantidad' => 2]);

    $linea = $this->reposicion->previsualizar('2026-07-01', '2026-12-15')->first();

    expect($linea['motivo'])->toBe(MotivoReposicion::Solicitada)
        ->and($linea['cantidad'])->toBe(4)
        ->and($linea['item_inventario_id'])->toBeNull();
});

// ---------------------------------------------------------------------
// Cerrar la lista
// ---------------------------------------------------------------------

it('congela las líneas al cerrar la lista', function (): void {
    $gasas = itemCon('Gasas estériles', 100, $this->administrativo);
    $this->travelTo('2026-09-10');
    $this->inventario->retirarUnidades($this->administrativo, $gasas, 40, 'Prácticas del semestre.');
    $this->travelBack();

    $lista = ListaDeReposicion::factory()->create();
    $lista = $this->reposicion->cerrar($this->coordinadora, $lista, 'Solicitud del semestre 2026-2.');

    expect($lista->estaCerrada())->toBeTrue()
        ->and($lista->cerrada_por)->toBe($this->coordinadora->id)
        ->and($lista->lineas)->toHaveCount(1)
        ->and($lista->lineas->first()->cantidad)->toBe(40);
});

it('no cambia una lista cerrada aunque el inventario siga moviéndose', function (): void {
    // Es la razón de que la lista sea un documento y no una vista: la carta
    // ya se entregó.
    $gasas = itemCon('Gasas estériles', 100, $this->administrativo);
    $this->travelTo('2026-09-10');
    $this->inventario->retirarUnidades($this->administrativo, $gasas, 40, 'Prácticas del semestre.');
    $this->travelBack();

    $lista = $this->reposicion->cerrar($this->coordinadora, ListaDeReposicion::factory()->create());

    $this->travelTo('2026-10-10');
    $this->inventario->retirarUnidades($this->administrativo, $gasas, 25, 'Más prácticas.');
    $this->travelBack();

    expect($lista->fresh()->lineas->first()->cantidad)->toBe(40);
});

it('conserva el nombre que el ítem tenía al cerrarse', function (): void {
    $gasas = itemCon('Gasas estériles', 100, $this->administrativo);
    $this->travelTo('2026-09-10');
    $this->inventario->retirarUnidades($this->administrativo, $gasas, 10, 'Prácticas.');
    $this->travelBack();

    $lista = $this->reposicion->cerrar($this->coordinadora, ListaDeReposicion::factory()->create());
    $gasas->update(['nombre' => 'Gasas estériles 10x10']);

    expect($lista->fresh()->lineas->first()->descripcion)->toBe('Gasas estériles');
});

it('exporta la descripción congelada, no el nombre actual del ítem', function (): void {
    // Lo que se descarga tiene que ser lo que se entregó. El PDF y el Excel
    // comparten el mismo map(), así que esto cubre las dos salidas.
    $gasas = itemCon('Gasas estériles', 100, $this->administrativo);
    $this->travelTo('2026-09-10');
    $this->inventario->retirarUnidades($this->administrativo, $gasas, 10, 'Prácticas.');
    $this->travelBack();
    $lista = $this->reposicion->cerrar($this->coordinadora, ListaDeReposicion::factory()->create());

    $gasas->update(['nombre' => 'Gasas estériles 10x10']);

    $filas = app(GeneradorDeReportes::class)->filas(
        Reporte::ListaDeReposicion,
        new FiltroReporte(listaDeReposicionId: $lista->id),
    );

    expect($filas->first())->toBe(['Gasas estériles', 'Consumo', 10, 'Sí']);
});

it('exporta solo las líneas de la lista pedida', function (): void {
    $otra = ListaDeReposicion::factory()->cerrada()->create();
    LineaDeReposicion::factory()->count(3)->create([
        'lista_reposicion_id' => $otra->id,
        'descripcion' => 'De la otra lista',
    ]);

    $gasas = itemCon('Gasas estériles', 100, $this->administrativo);
    $this->travelTo('2026-09-10');
    $this->inventario->retirarUnidades($this->administrativo, $gasas, 10, 'Prácticas.');
    $this->travelBack();
    $lista = $this->reposicion->cerrar($this->coordinadora, ListaDeReposicion::factory()->create());

    $filas = app(GeneradorDeReportes::class)->filas(
        Reporte::ListaDeReposicion,
        new FiltroReporte(listaDeReposicionId: $lista->id),
    );

    expect($filas)->toHaveCount(1)
        ->and($filas->first()[0])->toBe('Gasas estériles');
});

it('no deja cerrar dos veces la misma lista', function (): void {
    $gasas = itemCon('Gasas estériles', 100, $this->administrativo);
    $this->travelTo('2026-09-10');
    $this->inventario->retirarUnidades($this->administrativo, $gasas, 10, 'Prácticas.');
    $this->travelBack();

    $lista = $this->reposicion->cerrar($this->coordinadora, ListaDeReposicion::factory()->create());

    expect(fn () => $this->reposicion->cerrar($this->coordinadora, $lista))
        ->toThrow(ReposicionInvalida::class, 'ya está cerrada');

    expect($lista->fresh()->lineas)->toHaveCount(1);
});

it('no deja cerrar una lista vacía', function (): void {
    expect(fn () => $this->reposicion->cerrar($this->coordinadora, ListaDeReposicion::factory()->create()))
        ->toThrow(ReposicionInvalida::class, 'No hay nada que pedir');
});

// ---------------------------------------------------------------------
// Permisos
// ---------------------------------------------------------------------

it('deja ver y preparar la lista a administrativos, coordinación y ADMIN', function (Rol $rol): void {
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);

    expect($usuario->can('viewAny', ListaDeReposicion::class))->toBeTrue()
        ->and($usuario->can('create', NecesidadDeReposicion::class))->toBeTrue();
})->with([Rol::Administrativo, Rol::Coordinador, Rol::Admin]);

it('no deja a un docente ni a un estudiante acercarse a la lista', function (Rol $rol): void {
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);

    expect($usuario->can('viewAny', ListaDeReposicion::class))->toBeFalse()
        ->and($usuario->can('create', NecesidadDeReposicion::class))->toBeFalse();

    $this->actingAs($usuario->fresh())->get(route('panel.reposicion'))->assertForbidden();
})->with([Rol::Docente, Rol::Estudiante]);

it('no deja a un docente anotar una necesidad ni llamando al Service', function (): void {
    $docente = User::factory()->docente()->create();

    expect(fn () => $this->reposicion->registrarNecesidad($docente, new DatosNecesidad(
        cantidad: 1,
        justificacion: 'Quiero pilas.',
        descripcion: 'Pila CR2032',
    )))->toThrow(AuthorizationException::class);

    expect(NecesidadDeReposicion::count())->toBe(0);
});

it('reserva cerrar la lista a coordinación y al ADMIN', function (Rol $rol, bool $puede): void {
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);

    expect($usuario->can('cerrar', ListaDeReposicion::factory()->create()))->toBe($puede);
})->with([
    'coordinador' => [Rol::Coordinador, true],
    'admin' => [Rol::Admin, true],
    'administrativo' => [Rol::Administrativo, false],
]);

it('no deja al administrativo cerrar la lista ni llamando al Service', function (): void {
    $gasas = itemCon('Gasas estériles', 100, $this->administrativo);
    $this->travelTo('2026-09-10');
    $this->inventario->retirarUnidades($this->administrativo, $gasas, 10, 'Prácticas.');
    $this->travelBack();
    $lista = ListaDeReposicion::factory()->create();

    expect(fn () => $this->reposicion->cerrar($this->administrativo, $lista))
        ->toThrow(AuthorizationException::class);

    expect($lista->fresh()->estaCerrada())->toBeFalse();
});

// ---------------------------------------------------------------------
// La pantalla y las descargas
// ---------------------------------------------------------------------

it('enseña la previsualización al administrativo', function (): void {
    $gasas = itemCon('Gasas estériles', 100, $this->administrativo);
    $this->travelTo('2026-09-10');
    $this->inventario->retirarUnidades($this->administrativo, $gasas, 40, 'Prácticas.');
    $this->travelBack();

    Livewire::actingAs($this->administrativo)
        ->test(Pantalla::class)
        ->set('desde', '2026-07-01')
        ->set('hasta', '2026-12-15')
        ->assertSee('Gasas estériles')
        ->assertSee('Consumo');
});

it('no le ofrece cerrar la lista al administrativo', function (): void {
    Livewire::actingAs($this->administrativo)
        ->test(Pantalla::class)
        ->assertDontSee('Cerrar la lista del periodo');
});

it('deja al administrativo anotar una necesidad desde la pantalla', function (): void {
    Livewire::actingAs($this->administrativo)
        ->test(Pantalla::class)
        ->call('abrirFormulario')
        ->set('descripcion', 'Pila CR2032 para el control del desfibrilador')
        ->set('cantidad', 2)
        ->set('justificacion', 'Se pidió para la práctica y no había.')
        ->call('anotarNecesidad')
        ->assertHasNoErrors();

    expect(NecesidadDeReposicion::count())->toBe(1);
});

it('no deja descargar una lista todavía en borrador', function (): void {
    $lista = ListaDeReposicion::factory()->create();

    $this->actingAs($this->coordinadora)->get(route('panel.reposicion.excel', $lista))->assertNotFound();
    $this->actingAs($this->coordinadora)->get(route('panel.reposicion.pdf', $lista))->assertNotFound();
});

it('deja descargar el soporte de una lista cerrada', function (string $formato): void {
    $gasas = itemCon('Gasas estériles', 100, $this->administrativo);
    $this->travelTo('2026-09-10');
    $this->inventario->retirarUnidades($this->administrativo, $gasas, 40, 'Prácticas.');
    $this->travelBack();
    $lista = $this->reposicion->cerrar($this->coordinadora, ListaDeReposicion::factory()->create());

    $this->actingAs($this->administrativo)
        ->get(route("panel.reposicion.{$formato}", $lista))
        ->assertOk();
})->with(['excel', 'pdf']);

it('no deja a un docente descargar el soporte', function (): void {
    $gasas = itemCon('Gasas estériles', 100, $this->administrativo);
    $this->travelTo('2026-09-10');
    $this->inventario->retirarUnidades($this->administrativo, $gasas, 40, 'Prácticas.');
    $this->travelBack();
    $lista = $this->reposicion->cerrar($this->coordinadora, ListaDeReposicion::factory()->create());

    $this->actingAs(User::factory()->docente()->create())
        ->get(route('panel.reposicion.excel', $lista))
        ->assertForbidden();
});
