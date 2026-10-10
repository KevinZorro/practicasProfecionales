<?php

declare(strict_types=1);

use App\Filament\Resources\EquipoDestacadoResource;
use App\Filament\Resources\EquipoDestacadoResource\Pages\CreateEquipoDestacado;
use App\Filament\Resources\EquipoDestacadoResource\Pages\EditEquipoDestacado;
use App\Filament\Resources\EquipoDestacadoResource\Pages\ListEquiposDestacados;
use App\Models\EquipoDestacado;
use App\Models\User;
use App\Services\ImagenPublicaService;
use Database\Seeders\RolSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Equipamiento destacado de la portada (RF01, RF10), en el panel del ADMIN.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    Storage::fake(ImagenPublicaService::DISCO);
    $this->admin = User::factory()->admin()->create();
});

it('deja al ADMIN gestionar el equipamiento destacado, incluido borrar y reordenar', function (): void {
    $equipo = EquipoDestacado::factory()->create();

    expect($this->admin->can('viewAny', EquipoDestacado::class))->toBeTrue()
        ->and($this->admin->can('create', EquipoDestacado::class))->toBeTrue()
        ->and($this->admin->can('update', $equipo))->toBeTrue()
        ->and($this->admin->can('delete', $equipo))->toBeTrue()
        ->and($this->admin->can('reorder', EquipoDestacado::class))->toBeTrue();
});

it('no deja gestionar el equipamiento destacado a ningún otro rol', function (string $estado): void {
    $usuario = User::factory()->{$estado}()->create();
    $equipo = EquipoDestacado::factory()->create();

    expect($usuario->can('viewAny', EquipoDestacado::class))->toBeFalse()
        ->and($usuario->can('create', EquipoDestacado::class))->toBeFalse()
        ->and($usuario->can('update', $equipo))->toBeFalse()
        ->and($usuario->can('delete', $equipo))->toBeFalse();
})->with(['coordinador', 'administrativo', 'docente', 'estudiante']);

it('abre el listado, el alta y la edición al ADMIN', function (): void {
    $equipo = EquipoDestacado::factory()->create();
    $this->actingAs($this->admin);

    expect(EquipoDestacadoResource::getUrl('index'))->toEndWith('/admin/equipamiento-destacado');

    $this->get(EquipoDestacadoResource::getUrl('index'))->assertOk();
    $this->get(EquipoDestacadoResource::getUrl('create'))->assertOk();
    $this->get(EquipoDestacadoResource::getUrl('edit', ['record' => $equipo]))->assertOk();
});

it('no abre el equipamiento destacado a otro rol', function (): void {
    $this->actingAs(User::factory()->coordinador()->create())
        ->get(EquipoDestacadoResource::getUrl('index'))
        ->assertForbidden();
});

it('crea un equipo con su foto y sus características, y lo pone al final', function (): void {
    // Con nombre propio: la factory podría elegir «Sala inmersiva» al azar.
    EquipoDestacado::factory()->create(['nombre' => 'Otro equipo', 'orden' => 3]);

    Livewire::actingAs($this->admin)
        ->test(CreateEquipoDestacado::class)
        ->fillForm([
            'nombre' => 'Sala inmersiva',
            'resumen' => 'Paredes que se convierten en urgencias o quirófano.',
            'imagen' => UploadedFile::fake()->image('sala.jpg', 1200, 800),
            // El repetidor simple guarda cada fila con su campo; al registro
            // llega como una lista plana.
            'caracteristicas' => [['caracteristica' => 'Entornos proyectados'], ['caracteristica' => 'Sonido ambiente']],
            'activo' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $equipo = EquipoDestacado::where('nombre', 'Sala inmersiva')->firstOrFail();

    expect($equipo->orden)->toBe(4)
        ->and($equipo->caracteristicas)->toBe(['Entornos proyectados', 'Sonido ambiente'])
        ->and($equipo->imagen)->toStartWith('equipamiento/')->toEndWith('.webp');
    Storage::disk(ImagenPublicaService::DISCO)->assertExists($equipo->imagen);
});

it('no crea un equipo sin nombre ni frase', function (): void {
    Livewire::actingAs($this->admin)
        ->test(CreateEquipoDestacado::class)
        ->fillForm(['nombre' => '', 'resumen' => ''])
        ->call('create')
        ->assertHasFormErrors(['nombre' => 'required', 'resumen' => 'required']);

    expect(EquipoDestacado::count())->toBe(0);
});

it('no acepta más de cuatro características', function (): void {
    Livewire::actingAs($this->admin)
        ->test(CreateEquipoDestacado::class)
        ->fillForm([
            'nombre' => 'Mesa de anatomía virtual',
            'resumen' => 'El cuerpo humano capa por capa.',
            'caracteristicas' => array_map(
                static fn (string $texto): array => ['caracteristica' => $texto],
                ['Una', 'Dos', 'Tres', 'Cuatro', 'Cinco'],
            ),
        ])
        ->call('create')
        ->assertHasFormErrors(['caracteristicas']);

    expect(EquipoDestacado::count())->toBe(0);
});

it('borra la foto anterior al reemplazarla', function (): void {
    Storage::disk(ImagenPublicaService::DISCO)->put('equipamiento/vieja.webp', 'x');
    $equipo = EquipoDestacado::factory()->create(['imagen' => 'equipamiento/vieja.webp']);

    Livewire::actingAs($this->admin)
        ->test(EditEquipoDestacado::class, ['record' => $equipo->getRouteKey()])
        ->set('data.imagen', [UploadedFile::fake()->image('nueva.jpg', 800, 600)])
        ->call('save')
        ->assertHasNoFormErrors();

    Storage::disk(ImagenPublicaService::DISCO)->assertMissing('equipamiento/vieja.webp');
});

it('borra el equipo y su foto', function (): void {
    Storage::disk(ImagenPublicaService::DISCO)->put('equipamiento/mesa.webp', 'x');
    $equipo = EquipoDestacado::factory()->create(['imagen' => 'equipamiento/mesa.webp']);

    Livewire::actingAs($this->admin)
        ->test(ListEquiposDestacados::class)
        ->callTableAction('delete', $equipo);

    expect(EquipoDestacado::count())->toBe(0);
    Storage::disk(ImagenPublicaService::DISCO)->assertMissing('equipamiento/mesa.webp');
});

it('reordena el equipamiento arrastrando las filas', function (): void {
    $primero = EquipoDestacado::factory()->create(['orden' => 1]);
    $segundo = EquipoDestacado::factory()->create(['orden' => 2]);

    Livewire::actingAs($this->admin)
        ->test(ListEquiposDestacados::class)
        ->call('reorderTable', [(string) $segundo->id, (string) $primero->id]);

    expect(EquipoDestacado::orderBy('orden')->pluck('id')->all())->toBe([$segundo->id, $primero->id]);
});
