<?php

declare(strict_types=1);

use App\Filament\Resources\MateriaResource;
use App\Filament\Resources\MateriaResource\Pages\CreateMateria;
use App\Filament\Resources\MateriaResource\Pages\EditMateria;
use App\Filament\Resources\MateriaResource\Pages\ListMaterias;
use App\Models\Materia;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Livewire\Livewire;

/*
 * Materias en el panel del ADMIN (RF23). Las reglas de acceso las pone
 * MateriaPolicy; la puerta del panel la prueba PanelDelAdminTest.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->admin = User::factory()->admin()->create();
});

// ---------------------------------------------------------------------
// La Policy
// ---------------------------------------------------------------------

it('deja al ADMIN ver, crear y editar materias', function (): void {
    $materia = Materia::factory()->create();

    expect($this->admin->can('viewAny', Materia::class))->toBeTrue()
        ->and($this->admin->can('create', Materia::class))->toBeTrue()
        ->and($this->admin->can('update', $materia))->toBeTrue();
});

it('no deja gestionar materias a ningún otro rol, tampoco al coordinador', function (string $estado): void {
    $usuario = User::factory()->{$estado}()->create();
    $materia = Materia::factory()->create();

    expect($usuario->can('viewAny', Materia::class))->toBeFalse()
        ->and($usuario->can('create', Materia::class))->toBeFalse()
        ->and($usuario->can('update', $materia))->toBeFalse();
})->with(['coordinador', 'administrativo', 'docente', 'estudiante']);

it('no deja borrar materias a nadie, ni al ADMIN', function (): void {
    expect($this->admin->can('delete', Materia::factory()->create()))->toBeFalse()
        ->and($this->admin->can('deleteAny', Materia::class))->toBeFalse();
});

// ---------------------------------------------------------------------
// Las pantallas
// ---------------------------------------------------------------------

it('abre el listado, el alta y la edición al ADMIN', function (): void {
    $materia = Materia::factory()->create();
    $this->actingAs($this->admin);

    $this->get(MateriaResource::getUrl('index'))->assertOk();
    $this->get(MateriaResource::getUrl('create'))->assertOk();
    $this->get(MateriaResource::getUrl('edit', ['record' => $materia]))->assertOk();
});

it('no abre el listado a otro rol', function (): void {
    $this->actingAs(User::factory()->coordinador()->create())
        ->get(MateriaResource::getUrl('index'))
        ->assertForbidden();
});

it('lista las materias activas y las inactivas', function (): void {
    $activa = Materia::factory()->create();
    $inactiva = Materia::factory()->inactiva()->create();

    Livewire::actingAs($this->admin)
        ->test(ListMaterias::class)
        ->assertCanSeeTableRecords([$activa, $inactiva]);
});

it('crea una materia', function (): void {
    Livewire::actingAs($this->admin)
        ->test(CreateMateria::class)
        ->fillForm([
            'codigo' => 'ENF-301',
            'nombre' => 'Cuidado Materno Perinatal',
            'semestre' => 5,
            'activo' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Materia::where('codigo', 'ENF-301')->first())
        ->nombre->toBe('Cuidado Materno Perinatal')
        ->semestre->toBe(5)
        ->activo->toBeTrue();
});

it('no crea una materia sin sus datos obligatorios', function (): void {
    Livewire::actingAs($this->admin)
        ->test(CreateMateria::class)
        ->fillForm(['codigo' => '', 'nombre' => '', 'semestre' => null])
        ->call('create')
        ->assertHasFormErrors(['codigo' => 'required', 'nombre' => 'required', 'semestre' => 'required']);

    expect(Materia::count())->toBe(0);
});

it('no repite un código de materia', function (): void {
    Materia::factory()->create(['codigo' => 'ENF-301']);

    Livewire::actingAs($this->admin)
        ->test(CreateMateria::class)
        ->fillForm(['codigo' => 'ENF-301', 'nombre' => 'Otra', 'semestre' => 3])
        ->call('create')
        ->assertHasFormErrors(['codigo' => 'unique']);

    expect(Materia::count())->toBe(1);
});

it('no acepta un semestre fuera de rango', function (int $semestre): void {
    Livewire::actingAs($this->admin)
        ->test(CreateMateria::class)
        ->fillForm(['codigo' => 'ENF-301', 'nombre' => 'Semiología', 'semestre' => $semestre])
        ->call('create')
        ->assertHasFormErrors(['semestre']);
})->with([0, 256]);

it('edita una materia sin chocar con su propio código', function (): void {
    $materia = Materia::factory()->create(['codigo' => 'ENF-301']);

    Livewire::actingAs($this->admin)
        ->test(EditMateria::class, ['record' => $materia->getRouteKey()])
        ->fillForm(['nombre' => 'Urgencias y Trauma'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($materia->fresh())
        ->codigo->toBe('ENF-301')
        ->nombre->toBe('Urgencias y Trauma');
});

it('saca del formulario del docente la materia que el ADMIN desactiva', function (): void {
    $materia = Materia::factory()->create(['nombre' => 'Farmacología Aplicada Avanzada']);
    $docente = User::factory()->docente()->create();

    $this->actingAs($docente)->get(route('panel.solicitudes.nueva'))->assertSee('Farmacología Aplicada Avanzada');

    Livewire::actingAs($this->admin)
        ->test(EditMateria::class, ['record' => $materia->getRouteKey()])
        ->fillForm(['activo' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->actingAs($docente)->get(route('panel.solicitudes.nueva'))->assertDontSee('Farmacología Aplicada Avanzada');
});

it('no ofrece borrar materias en ninguna pantalla', function (): void {
    $materia = Materia::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(ListMaterias::class)
        ->assertTableActionDoesNotExist('delete')
        ->assertTableBulkActionDoesNotExist('delete');

    Livewire::actingAs($this->admin)
        ->test(EditMateria::class, ['record' => $materia->getRouteKey()])
        ->assertActionDoesNotExist('delete');
});
