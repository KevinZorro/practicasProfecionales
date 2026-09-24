<?php

declare(strict_types=1);

use App\Filament\Resources\CasoClinicoResource;
use App\Filament\Resources\CasoClinicoResource\Pages\CreateCasoClinico;
use App\Filament\Resources\CasoClinicoResource\Pages\EditCasoClinico;
use App\Filament\Resources\CasoClinicoResource\Pages\ListCasosClinicos;
use App\Models\CasoClinico;
use App\Models\ItemInventario;
use App\Models\ItemNecesarioDelCaso;
use App\Models\Materia;
use App\Models\User;
use App\Services\SolicitudService;
use Database\Seeders\RolSeeder;
use Livewire\Livewire;

/*
 * Casos clínicos en el panel del ADMIN: ficha, materias (RF24), inventario
 * que necesitan (RF25) y capacidad máxima de estudiantes (RF74), que antes
 * tenía una pantalla Livewire propia.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->admin = User::factory()->admin()->create();
});

/** @param array<string, mixed> $datos */
function crearCasoClinico(array $datos): mixed
{
    return Livewire::actingAs(test()->admin)
        ->test(CreateCasoClinico::class)
        ->fillForm([
            'nombre' => 'Atención de parto normal',
            'descripcion' => 'Parto eutócico con maniquí de alta fidelidad.',
            'activo' => true,
            ...$datos,
        ])
        ->call('create');
}

function editarCasoClinico(CasoClinico $caso): mixed
{
    return Livewire::actingAs(test()->admin)->test(EditCasoClinico::class, ['record' => $caso->getRouteKey()]);
}

// ---------------------------------------------------------------------
// La Policy
// ---------------------------------------------------------------------

it('deja al ADMIN ver, crear y editar casos clínicos', function (): void {
    $caso = CasoClinico::factory()->create();

    expect($this->admin->can('viewAny', CasoClinico::class))->toBeTrue()
        ->and($this->admin->can('create', CasoClinico::class))->toBeTrue()
        ->and($this->admin->can('update', $caso))->toBeTrue();
});

it('no deja gestionar casos clínicos a ningún otro rol, tampoco al coordinador', function (string $estado): void {
    $usuario = User::factory()->{$estado}()->create();
    $caso = CasoClinico::factory()->create();

    expect($usuario->can('viewAny', CasoClinico::class))->toBeFalse()
        ->and($usuario->can('create', CasoClinico::class))->toBeFalse()
        ->and($usuario->can('update', $caso))->toBeFalse();
})->with(['coordinador', 'administrativo', 'docente', 'estudiante']);

it('no deja borrar casos clínicos a nadie, ni al ADMIN', function (): void {
    expect($this->admin->can('delete', CasoClinico::factory()->create()))->toBeFalse()
        ->and($this->admin->can('deleteAny', CasoClinico::class))->toBeFalse();
});

// ---------------------------------------------------------------------
// Las pantallas
// ---------------------------------------------------------------------

it('abre el listado, el alta y la edición al ADMIN', function (): void {
    $caso = CasoClinico::factory()->create();
    $this->actingAs($this->admin);

    $this->get(CasoClinicoResource::getUrl('index'))->assertOk();
    $this->get(CasoClinicoResource::getUrl('create'))->assertOk();
    $this->get(CasoClinicoResource::getUrl('edit', ['record' => $caso]))->assertOk();
});

it('no abre el listado a otro rol', function (): void {
    $this->actingAs(User::factory()->coordinador()->create())
        ->get(CasoClinicoResource::getUrl('index'))
        ->assertForbidden();
});

it('vive en /admin/casos-clinicos', function (): void {
    expect(CasoClinicoResource::getUrl('index'))->toEndWith('/admin/casos-clinicos');
});

it('ya no sirve la pantalla de capacidad que sustituye', function (): void {
    $this->actingAs($this->admin)->get('/panel/casos-clinicos')->assertNotFound();
});

it('crea un caso clínico con sus materias y su inventario', function (): void {
    $materias = Materia::factory()->count(2)->create();
    $sonda = ItemInventario::factory()->create();
    $gasas = ItemInventario::factory()->create();

    crearCasoClinico([
        'capacidad_maxima_estudiantes' => 12,
        'materias' => $materias->pluck('id')->all(),
        'itemsNecesarios' => [
            ['item_inventario_id' => $sonda->id, 'cantidad' => 2],
            ['item_inventario_id' => $gasas->id, 'cantidad' => 10],
        ],
    ])->assertHasNoFormErrors();

    $caso = CasoClinico::where('nombre', 'Atención de parto normal')->firstOrFail();

    expect($caso->capacidad_maxima_estudiantes)->toBe(12)
        ->and($caso->materias->pluck('id')->sort()->values()->all())->toBe($materias->pluck('id')->sort()->values()->all())
        ->and($caso->items->mapWithKeys(fn ($item): array => [$item->id => $item->pivot->cantidad])->all())
        ->toEqual([$sonda->id => 2, $gasas->id => 10]);
});

it('precarga en la solicitud el inventario que el ADMIN le puso al caso', function (): void {
    // Lo que se edita aquí es lo mismo que lee SolicitudService: el modelo de
    // las filas es otra vista de la misma tabla, no una copia.
    $sonda = ItemInventario::factory()->create();

    crearCasoClinico([
        'itemsNecesarios' => [['item_inventario_id' => $sonda->id, 'cantidad' => 3]],
    ])->assertHasNoFormErrors();

    $caso = CasoClinico::where('nombre', 'Atención de parto normal')->firstOrFail();
    $precarga = app(SolicitudService::class)->itemsSugeridos($caso);

    expect($precarga->pluck('pivot.cantidad', 'id')->all())->toEqual([$sonda->id => 3]);
});

it('no deja el caso a medias si falla al guardar su inventario', function (): void {
    // El panel guarda en una transacción: el caso, sus materias y su
    // inventario entran juntos o no entra ninguno.
    $sonda = ItemInventario::factory()->create();
    ItemNecesarioDelCaso::creating(static fn () => throw new RuntimeException('falla simulada'));

    expect(fn () => crearCasoClinico([
        'materias' => [Materia::factory()->create()->id],
        'itemsNecesarios' => [['item_inventario_id' => $sonda->id, 'cantidad' => 2]],
    ]))->toThrow(RuntimeException::class, 'falla simulada');

    expect(CasoClinico::count())->toBe(0);
});

it('no crea un caso clínico sin nombre ni descripción', function (): void {
    crearCasoClinico(['nombre' => '', 'descripcion' => ''])
        ->assertHasFormErrors(['nombre' => 'required', 'descripcion' => 'required']);

    expect(CasoClinico::count())->toBe(0);
});

it('no repite un ítem en el inventario del caso', function (): void {
    $sonda = ItemInventario::factory()->create();

    crearCasoClinico([
        'itemsNecesarios' => [
            ['item_inventario_id' => $sonda->id, 'cantidad' => 2],
            ['item_inventario_id' => $sonda->id, 'cantidad' => 5],
        ],
    ])->assertHasFormErrors();

    expect(CasoClinico::count())->toBe(0);
});

it('no acepta una cantidad de inventario menor que uno', function (): void {
    $sonda = ItemInventario::factory()->create();

    crearCasoClinico([
        'itemsNecesarios' => [['item_inventario_id' => $sonda->id, 'cantidad' => 0]],
    ])->assertHasFormErrors();

    expect(CasoClinico::count())->toBe(0);
});

it('cambia y quita ítems del inventario de un caso', function (): void {
    $sonda = ItemInventario::factory()->create();
    $gasas = ItemInventario::factory()->create();
    $caso = CasoClinico::factory()->create();
    $caso->items()->attach([$sonda->id => ['cantidad' => 2], $gasas->id => ['cantidad' => 10]]);

    $pantalla = editarCasoClinico($caso);
    $filas = $pantalla->get('data.itemsNecesarios');
    $claveDeLaSonda = collect($filas)->search(fn (array $fila): bool => $fila['item_inventario_id'] === $sonda->id);

    $pantalla->set('data.itemsNecesarios', [$claveDeLaSonda => [...$filas[$claveDeLaSonda], 'cantidad' => 4]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($caso->fresh()->items->mapWithKeys(fn ($item): array => [$item->id => $item->pivot->cantidad])->all())
        ->toEqual([$sonda->id => 4]);
});

it('enseña y conserva una materia ya asociada aunque después se haya desactivado', function (): void {
    // Si el selector filtrara las inactivas, seguiría asociada sin que el
    // ADMIN la viera en el caso.
    $inactiva = Materia::factory()->create(['nombre' => 'Semiologia Clinica']);
    $caso = CasoClinico::factory()->create();
    $caso->materias()->attach($inactiva);
    $inactiva->update(['activo' => false]);

    editarCasoClinico($caso)
        ->assertSee('Semiologia Clinica (inactiva)')
        ->fillForm(['nombre' => 'Crisis convulsiva'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($caso->fresh()->materias->pluck('id')->all())->toBe([$inactiva->id]);
});

it('saca del formulario del docente el caso que el ADMIN desactiva', function (): void {
    $caso = CasoClinico::factory()->create(['nombre' => 'Reanimación neonatal avanzada']);
    $docente = User::factory()->docente()->create();

    $this->actingAs($docente)->get(route('panel.solicitudes.nueva'))->assertSee('Reanimación neonatal avanzada');

    editarCasoClinico($caso)->fillForm(['activo' => false])->call('save')->assertHasNoFormErrors();

    $this->actingAs($docente)->get(route('panel.solicitudes.nueva'))->assertDontSee('Reanimación neonatal avanzada');
});

it('no ofrece borrar casos clínicos en ninguna pantalla', function (): void {
    $caso = CasoClinico::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(ListCasosClinicos::class)
        ->assertTableActionDoesNotExist('delete')
        ->assertTableBulkActionDoesNotExist('delete');

    editarCasoClinico($caso)->assertActionDoesNotExist('delete');
});

// ---------------------------------------------------------------------
// Capacidad máxima de estudiantes (RF74). Casos que venían de la pantalla
// Livewire que este recurso sustituye.
// ---------------------------------------------------------------------

it('registra la capacidad de un escenario que no la tenía', function (): void {
    $caso = CasoClinico::factory()->create(['capacidad_maxima_estudiantes' => null]);

    editarCasoClinico($caso)
        ->fillForm(['capacidad_maxima_estudiantes' => 15])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($caso->fresh()->capacidad_maxima_estudiantes)->toBe(15);
});

it('corrige una capacidad ya registrada', function (): void {
    $caso = CasoClinico::factory()->conCapacidad(7)->create();

    editarCasoClinico($caso)
        ->fillForm(['capacidad_maxima_estudiantes' => 10])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($caso->fresh()->capacidad_maxima_estudiantes)->toBe(10);
});

it('deja la capacidad sin definir si el ADMIN la vacía', function (): void {
    // Regla 10: sin definir no limita.
    $caso = CasoClinico::factory()->conCapacidad(7)->create();

    editarCasoClinico($caso)
        ->fillForm(['capacidad_maxima_estudiantes' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($caso->fresh()->capacidad_maxima_estudiantes)->toBeNull();
});

it('no guarda una capacidad que no sea un entero entre uno y el tope', function (mixed $valor): void {
    $caso = CasoClinico::factory()->conCapacidad(7)->create();

    editarCasoClinico($caso)
        ->fillForm(['capacidad_maxima_estudiantes' => $valor])
        ->call('save')
        ->assertHasFormErrors(['capacidad_maxima_estudiantes']);

    expect($caso->fresh()->capacidad_maxima_estudiantes)->toBe(7);
})->with(['cero' => 0, 'negativo' => -3, 'texto' => 'muchos', 'decimal' => '7.5', 'sobre el tope' => 201]);

it('marca en el listado el escenario sin capacidad definida', function (): void {
    CasoClinico::factory()->create(['nombre' => 'Morfofisiología', 'capacidad_maxima_estudiantes' => null]);

    Livewire::actingAs($this->admin)
        ->test(ListCasosClinicos::class)
        ->assertSee('Morfofisiología')
        ->assertSee('Sin definir');
});

it('busca escenarios por nombre', function (): void {
    $parto = CasoClinico::factory()->create(['nombre' => 'Atención de parto normal']);
    $convulsion = CasoClinico::factory()->create(['nombre' => 'Crisis convulsiva']);

    Livewire::actingAs($this->admin)
        ->test(ListCasosClinicos::class)
        ->searchTable('parto')
        ->assertCanSeeTableRecords([$parto])
        ->assertCanNotSeeTableRecords([$convulsion]);
});
