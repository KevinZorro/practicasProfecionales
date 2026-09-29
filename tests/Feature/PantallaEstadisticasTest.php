<?php

declare(strict_types=1);

use App\Filament\Resources\EstadisticaLandingResource;
use App\Filament\Resources\EstadisticaLandingResource\Pages\CreateEstadisticaLanding;
use App\Filament\Resources\EstadisticaLandingResource\Pages\EditEstadisticaLanding;
use App\Filament\Resources\EstadisticaLandingResource\Pages\ListEstadisticasLanding;
use App\Models\EstadisticaLanding;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Livewire\Livewire;

/*
 * Estadísticas de la landing (RF01, RF10): cifras que el ADMIN escribe a mano.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->admin = User::factory()->admin()->create();
});

it('deja al ADMIN gestionar estadísticas', function (): void {
    $estadistica = EstadisticaLanding::factory()->create();

    expect($this->admin->can('viewAny', EstadisticaLanding::class))->toBeTrue()
        ->and($this->admin->can('create', EstadisticaLanding::class))->toBeTrue()
        ->and($this->admin->can('update', $estadistica))->toBeTrue()
        ->and($this->admin->can('delete', $estadistica))->toBeTrue()
        ->and($this->admin->can('reorder', EstadisticaLanding::class))->toBeTrue();
});

it('no deja gestionar estadísticas a ningún otro rol', function (string $estado): void {
    $usuario = User::factory()->{$estado}()->create();
    $estadistica = EstadisticaLanding::factory()->create();

    expect($usuario->can('viewAny', EstadisticaLanding::class))->toBeFalse()
        ->and($usuario->can('create', EstadisticaLanding::class))->toBeFalse()
        ->and($usuario->can('update', $estadistica))->toBeFalse()
        ->and($usuario->can('delete', $estadistica))->toBeFalse()
        ->and($usuario->can('reorder', EstadisticaLanding::class))->toBeFalse();
})->with(['coordinador', 'administrativo', 'docente', 'estudiante']);

it('abre el listado, el alta y la edición al ADMIN', function (): void {
    $estadistica = EstadisticaLanding::factory()->create();
    $this->actingAs($this->admin);

    expect(EstadisticaLandingResource::getUrl('index'))->toEndWith('/admin/estadisticas');

    $this->get(EstadisticaLandingResource::getUrl('index'))->assertOk();
    $this->get(EstadisticaLandingResource::getUrl('create'))->assertOk();
    $this->get(EstadisticaLandingResource::getUrl('edit', ['record' => $estadistica]))->assertOk();
});

it('no abre las estadísticas a otro rol', function (): void {
    $this->actingAs(User::factory()->coordinador()->create())
        ->get(EstadisticaLandingResource::getUrl('index'))
        ->assertForbidden();
});

it('guarda el valor tal cual lo escribe el ADMIN y lo pone al final', function (): void {
    EstadisticaLanding::factory()->create(['orden' => 4]);

    Livewire::actingAs($this->admin)
        ->test(CreateEstadisticaLanding::class)
        ->fillForm(['valor' => '+700', 'etiqueta' => 'estudiantes por semestre', 'activo' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $estadistica = EstadisticaLanding::where('etiqueta', 'estudiantes por semestre')->firstOrFail();

    expect($estadistica->valor)->toBe('+700')
        ->and($estadistica->orden)->toBe(5)
        ->and($estadistica->activo)->toBeTrue();
});

it('no crea una estadística sin valor ni etiqueta', function (): void {
    Livewire::actingAs($this->admin)
        ->test(CreateEstadisticaLanding::class)
        ->fillForm(['valor' => '', 'etiqueta' => ''])
        ->call('create')
        ->assertHasFormErrors(['valor' => 'required', 'etiqueta' => 'required']);

    expect(EstadisticaLanding::count())->toBe(0);
});

it('no acepta un valor que no cabe como cifra destacada', function (): void {
    Livewire::actingAs($this->admin)
        ->test(CreateEstadisticaLanding::class)
        ->fillForm(['valor' => str_repeat('9', 31), 'etiqueta' => 'simuladores'])
        ->call('create')
        ->assertHasFormErrors(['valor' => 'max']);

    expect(EstadisticaLanding::count())->toBe(0);
});

it('edita el valor sin cambiar el orden', function (): void {
    $estadistica = EstadisticaLanding::factory()->create(['valor' => '20', 'orden' => 3]);

    Livewire::actingAs($this->admin)
        ->test(EditEstadisticaLanding::class, ['record' => $estadistica->getRouteKey()])
        ->fillForm(['valor' => '22'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($estadistica->fresh()->valor)->toBe('22')
        ->and($estadistica->fresh()->orden)->toBe(3);
});

it('saca de la landing una estadística despublicada, sin borrarla', function (): void {
    $publicada = EstadisticaLanding::factory()->create(['orden' => 1]);
    $retirada = EstadisticaLanding::factory()->create(['orden' => 2]);

    Livewire::actingAs($this->admin)
        ->test(EditEstadisticaLanding::class, ['record' => $retirada->getRouteKey()])
        ->fillForm(['activo' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(EstadisticaLanding::publicadas()->pluck('id')->all())->toBe([$publicada->id])
        ->and(EstadisticaLanding::count())->toBe(2);
});

it('borra una estadística', function (): void {
    $estadistica = EstadisticaLanding::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(ListEstadisticasLanding::class)
        ->callTableAction('delete', $estadistica);

    expect(EstadisticaLanding::count())->toBe(0);
});

it('reordena las estadísticas arrastrando las filas, y la landing sigue ese orden', function (): void {
    $primera = EstadisticaLanding::factory()->create(['orden' => 1]);
    $segunda = EstadisticaLanding::factory()->create(['orden' => 2]);

    Livewire::actingAs($this->admin)
        ->test(ListEstadisticasLanding::class)
        ->call('reorderTable', [(string) $segunda->id, (string) $primera->id]);

    expect(EstadisticaLanding::publicadas()->pluck('id')->all())->toBe([$segunda->id, $primera->id]);
});
