<?php

declare(strict_types=1);

use App\Exceptions\EvaluacionInvalida;
use App\Filament\Resources\TipoEvaluacionResource;
use App\Filament\Resources\TipoEvaluacionResource\Pages\CreateTipoEvaluacion;
use App\Filament\Resources\TipoEvaluacionResource\Pages\EditTipoEvaluacion;
use App\Filament\Resources\TipoEvaluacionResource\Pages\ListTiposEvaluacion;
use App\Models\ItemChecklist;
use App\Models\Materia;
use App\Models\Solicitud;
use App\Models\TipoEvaluacion;
use App\Models\User;
use App\Services\EvaluacionService;
use Database\Seeders\RolSeeder;
use Livewire\Livewire;

/*
 * Tipos de evaluación en el panel del ADMIN (RF26): la plantilla del
 * checklist y las materias en que se puede usar (RF43).
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->admin = User::factory()->admin()->create();
});

function editarTipoEvaluacion(TipoEvaluacion $tipo): mixed
{
    return Livewire::actingAs(test()->admin)->test(EditTipoEvaluacion::class, ['record' => $tipo->getRouteKey()]);
}

/** @return list<string> descripciones del checklist, en su orden */
function checklistDe(TipoEvaluacion $tipo): array
{
    return $tipo->fresh()->itemsChecklist->pluck('descripcion')->all();
}

// ---------------------------------------------------------------------
// La Policy
// ---------------------------------------------------------------------

it('deja al ADMIN ver, crear y editar tipos de evaluación', function (): void {
    $tipo = TipoEvaluacion::factory()->create();

    expect($this->admin->can('viewAny', TipoEvaluacion::class))->toBeTrue()
        ->and($this->admin->can('create', TipoEvaluacion::class))->toBeTrue()
        ->and($this->admin->can('update', $tipo))->toBeTrue();
});

it('no deja gestionar tipos de evaluación a ningún otro rol, tampoco al coordinador', function (string $estado): void {
    $usuario = User::factory()->{$estado}()->create();
    $tipo = TipoEvaluacion::factory()->create();

    expect($usuario->can('viewAny', TipoEvaluacion::class))->toBeFalse()
        ->and($usuario->can('create', TipoEvaluacion::class))->toBeFalse()
        ->and($usuario->can('update', $tipo))->toBeFalse();
})->with(['coordinador', 'administrativo', 'docente', 'estudiante']);

it('no deja borrar tipos de evaluación a nadie, ni al ADMIN', function (): void {
    expect($this->admin->can('delete', TipoEvaluacion::factory()->create()))->toBeFalse()
        ->and($this->admin->can('deleteAny', TipoEvaluacion::class))->toBeFalse();
});

// ---------------------------------------------------------------------
// Las pantallas
// ---------------------------------------------------------------------

it('vive en /admin/tipos-de-evaluacion', function (): void {
    expect(TipoEvaluacionResource::getUrl('index'))->toEndWith('/admin/tipos-de-evaluacion');
});

it('abre el listado, el alta y la edición al ADMIN', function (): void {
    $tipo = TipoEvaluacion::factory()->create();
    $this->actingAs($this->admin);

    $this->get(TipoEvaluacionResource::getUrl('index'))->assertOk();
    $this->get(TipoEvaluacionResource::getUrl('create'))->assertOk();
    $this->get(TipoEvaluacionResource::getUrl('edit', ['record' => $tipo]))->assertOk();
});

it('no abre el listado a otro rol', function (): void {
    $this->actingAs(User::factory()->coordinador()->create())
        ->get(TipoEvaluacionResource::getUrl('index'))
        ->assertForbidden();
});

it('crea un tipo de evaluación con sus materias y su checklist en orden', function (): void {
    $materia = Materia::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(CreateTipoEvaluacion::class)
        ->fillForm([
            'nombre' => 'Canalización de vía periférica',
            'descripcion' => 'Técnica aséptica en adulto.',
            'activo' => true,
            'materias' => [$materia->id],
            'itemsChecklist' => [
                ['descripcion' => 'Lavado de manos'],
                ['descripcion' => 'Selección del sitio'],
                ['descripcion' => 'Fijación del catéter'],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $tipo = TipoEvaluacion::where('nombre', 'Canalización de vía periférica')->firstOrFail();

    expect($tipo->materias->pluck('id')->all())->toBe([$materia->id])
        ->and(checklistDe($tipo))->toBe(['Lavado de manos', 'Selección del sitio', 'Fijación del catéter'])
        ->and($tipo->itemsChecklist->pluck('orden')->all())->toBe([1, 2, 3]);
});

it('no crea un tipo de evaluación sin nombre', function (): void {
    Livewire::actingAs($this->admin)
        ->test(CreateTipoEvaluacion::class)
        ->fillForm(['nombre' => ''])
        ->call('create')
        ->assertHasFormErrors(['nombre' => 'required']);

    expect(TipoEvaluacion::count())->toBe(0);
});

it('no guarda un ítem del checklist vacío', function (): void {
    Livewire::actingAs($this->admin)
        ->test(CreateTipoEvaluacion::class)
        ->fillForm(['nombre' => 'Canalización de vía periférica', 'itemsChecklist' => [['descripcion' => '']]])
        ->call('create')
        ->assertHasFormErrors();

    expect(TipoEvaluacion::count())->toBe(0)
        ->and(ItemChecklist::count())->toBe(0);
});

it('reordena el checklist', function (): void {
    $tipo = TipoEvaluacion::factory()->create();
    foreach (['Primero', 'Segundo', 'Tercero'] as $posicion => $descripcion) {
        ItemChecklist::factory()->create(['tipo_evaluacion_id' => $tipo->id, 'descripcion' => $descripcion, 'orden' => $posicion + 1]);
    }

    $pantalla = editarTipoEvaluacion($tipo);
    $filas = $pantalla->get('data.itemsChecklist');
    $claves = array_keys($filas);

    // Lo que hace arrastrar el último ítem al principio.
    $pantalla->set('data.itemsChecklist', [
        $claves[2] => $filas[$claves[2]],
        $claves[0] => $filas[$claves[0]],
        $claves[1] => $filas[$claves[1]],
    ])->call('save')->assertHasNoFormErrors();

    expect(checklistDe($tipo))->toBe(['Tercero', 'Primero', 'Segundo']);
});

it('no cambia el checklist de una evaluación ya creada al editar la plantilla', function (): void {
    // Regla 3: los ítems se copian, no se referencian. Aquí el ADMIN
    // reescribe un ítem y quita otro después de que el docente evaluó.
    $materia = Materia::factory()->create();
    $tipo = TipoEvaluacion::factory()->create();
    $tipo->materias()->attach($materia);
    foreach (['Lavado de manos', 'Selección del sitio'] as $posicion => $descripcion) {
        ItemChecklist::factory()->create(['tipo_evaluacion_id' => $tipo->id, 'descripcion' => $descripcion, 'orden' => $posicion + 1]);
    }

    $solicitud = Solicitud::factory()->deEvaluacion()->aprobada()->create(['materia_id' => $materia->id]);
    $evaluacion = app(EvaluacionService::class)->crear($solicitud, $tipo, User::factory()->docente()->create());

    $pantalla = editarTipoEvaluacion($tipo);
    $filas = $pantalla->get('data.itemsChecklist');
    $primera = array_key_first($filas);

    $pantalla->set('data.itemsChecklist', [$primera => ['descripcion' => 'Higiene de manos con alcohol']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(checklistDe($tipo))->toBe(['Higiene de manos con alcohol'])
        ->and($evaluacion->fresh()->items->pluck('descripcion')->all())->toBe(['Lavado de manos', 'Selección del sitio']);
});

it('habilita el tipo solo en las materias que el ADMIN marca', function (): void {
    // RF43: EvaluacionService rechaza un tipo que no está en la materia de
    // la solicitud. Lo que se marca aquí es lo que consulta.
    $habilitada = Materia::factory()->create();
    $otra = Materia::factory()->create();
    $tipo = TipoEvaluacion::factory()->create();

    editarTipoEvaluacion($tipo)
        ->fillForm(['materias' => [$habilitada->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    $docente = User::factory()->docente()->create();
    $enLaHabilitada = Solicitud::factory()->deEvaluacion()->aprobada()->create(['materia_id' => $habilitada->id]);
    $enLaOtra = Solicitud::factory()->deEvaluacion()->aprobada()->create(['materia_id' => $otra->id]);

    expect(app(EvaluacionService::class)->crear($enLaHabilitada, $tipo, $docente)->exists)->toBeTrue()
        ->and(fn () => app(EvaluacionService::class)->crear($enLaOtra, $tipo, $docente))->toThrow(EvaluacionInvalida::class);
});

it('enseña y conserva una materia ya asociada aunque después se haya desactivado', function (): void {
    $inactiva = Materia::factory()->create(['nombre' => 'Semiologia Clinica']);
    $tipo = TipoEvaluacion::factory()->create();
    $tipo->materias()->attach($inactiva);
    $inactiva->update(['activo' => false]);

    editarTipoEvaluacion($tipo)
        ->assertSee('Semiologia Clinica (inactiva)')
        ->fillForm(['nombre' => 'Otro nombre'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($tipo->fresh()->materias->pluck('id')->all())->toBe([$inactiva->id]);
});

it('no ofrece borrar tipos de evaluación en ninguna pantalla', function (): void {
    $tipo = TipoEvaluacion::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(ListTiposEvaluacion::class)
        ->assertTableActionDoesNotExist('delete')
        ->assertTableBulkActionDoesNotExist('delete');

    editarTipoEvaluacion($tipo)->assertActionDoesNotExist('delete');
});
