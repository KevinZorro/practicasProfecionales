<?php

declare(strict_types=1);

use App\Enums\ModalidadTaller;
use App\Filament\Resources\TallerResource;
use App\Filament\Resources\TallerResource\Pages\CreateTaller;
use App\Filament\Resources\TallerResource\Pages\EditTaller;
use App\Filament\Resources\TallerResource\Pages\ListTalleres;
use App\Models\SolicitudInformacion;
use App\Models\Taller;
use App\Models\User;
use App\Services\ImagenPublicaService;
use Database\Seeders\RolSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Talleres de la landing (RF04, RF13), en el panel del ADMIN.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    Storage::fake(ImagenPublicaService::DISCO);
    $this->admin = User::factory()->admin()->create();
});

/** @param array<string, mixed> $datos */
function datosDeTaller(array $datos = []): array
{
    return [
        'titulo' => 'Taller de reanimación cardiopulmonar',
        'descripcion' => 'Práctica con maniquí de alta fidelidad.',
        'tema' => 'Urgencias',
        'fecha' => '2026-11-15',
        'modalidad' => ModalidadTaller::Presencial->value,
        'muestra_formulario' => true,
        'activo' => true,
        ...$datos,
    ];
}

// ---------------------------------------------------------------------
// La Policy
// ---------------------------------------------------------------------

it('deja al ADMIN gestionar talleres', function (): void {
    $taller = Taller::factory()->create();

    expect($this->admin->can('viewAny', Taller::class))->toBeTrue()
        ->and($this->admin->can('create', Taller::class))->toBeTrue()
        ->and($this->admin->can('update', $taller))->toBeTrue()
        ->and($this->admin->can('delete', $taller))->toBeTrue()
        ->and($this->admin->can('reorder', Taller::class))->toBeTrue();
});

it('no deja gestionar talleres a ningún otro rol', function (string $estado): void {
    $usuario = User::factory()->{$estado}()->create();
    $taller = Taller::factory()->create();

    expect($usuario->can('viewAny', Taller::class))->toBeFalse()
        ->and($usuario->can('create', Taller::class))->toBeFalse()
        ->and($usuario->can('update', $taller))->toBeFalse()
        ->and($usuario->can('delete', $taller))->toBeFalse();
})->with(['coordinador', 'administrativo', 'docente', 'estudiante']);

it('no deja borrar un taller en el que ya hay interesados', function (): void {
    // Borrarlo se llevaría los datos que esas personas dejaron (RF09).
    $taller = Taller::factory()->create();
    SolicitudInformacion::factory()->create(['taller_id' => $taller->id]);

    expect($this->admin->can('delete', $taller))->toBeFalse();
});

// ---------------------------------------------------------------------
// Las pantallas
// ---------------------------------------------------------------------

it('abre el listado, el alta y la edición al ADMIN', function (): void {
    $taller = Taller::factory()->create();
    $this->actingAs($this->admin);

    expect(TallerResource::getUrl('index'))->toEndWith('/admin/talleres');

    $this->get(TallerResource::getUrl('index'))->assertOk();
    $this->get(TallerResource::getUrl('create'))->assertOk();
    $this->get(TallerResource::getUrl('edit', ['record' => $taller]))->assertOk();
});

it('no abre los talleres a otro rol', function (): void {
    $this->actingAs(User::factory()->coordinador()->create())
        ->get(TallerResource::getUrl('index'))
        ->assertForbidden();
});

it('crea un taller con su imagen lista para la web y lo pone al final', function (): void {
    Taller::factory()->create(['titulo' => 'Taller anterior', 'orden' => 7]);

    Livewire::actingAs($this->admin)
        ->test(CreateTaller::class)
        ->fillForm(datosDeTaller(['imagen' => UploadedFile::fake()->image('rcp.jpg', 2400, 1600)]))
        ->call('create')
        ->assertHasNoFormErrors();

    $taller = Taller::where('titulo', 'Taller de reanimación cardiopulmonar')->firstOrFail();

    expect($taller->modalidad)->toBe(ModalidadTaller::Presencial)
        ->and($taller->fecha->format('Y-m-d'))->toBe('2026-11-15')
        ->and($taller->orden)->toBe(8)
        ->and($taller->imagen)->toStartWith('talleres/')->toEndWith('.webp');
    Storage::disk(ImagenPublicaService::DISCO)->assertExists($taller->imagen);
});

it('crea un taller sin imagen', function (): void {
    Livewire::actingAs($this->admin)
        ->test(CreateTaller::class)
        ->fillForm(datosDeTaller())
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Taller::firstOrFail()->imagen)->toBeNull();
});

it('no crea un taller sin sus datos obligatorios', function (): void {
    Livewire::actingAs($this->admin)
        ->test(CreateTaller::class)
        ->fillForm(datosDeTaller(['titulo' => '', 'descripcion' => '', 'tema' => '', 'fecha' => null, 'modalidad' => null]))
        ->call('create')
        ->assertHasFormErrors([
            'titulo' => 'required', 'descripcion' => 'required', 'tema' => 'required',
            'fecha' => 'required', 'modalidad' => 'required',
        ]);

    expect(Taller::count())->toBe(0);
});

it('no acepta una modalidad fuera de las dos del RF04', function (): void {
    Livewire::actingAs($this->admin)
        ->test(CreateTaller::class)
        ->fillForm(datosDeTaller(['modalidad' => 'mixta']))
        ->call('create')
        ->assertHasFormErrors(['modalidad']);

    expect(Taller::count())->toBe(0);
});

it('borra la imagen anterior al reemplazarla', function (): void {
    Storage::disk(ImagenPublicaService::DISCO)->put('talleres/vieja.webp', 'x');
    $taller = Taller::factory()->create(['imagen' => 'talleres/vieja.webp']);

    Livewire::actingAs($this->admin)
        ->test(EditTaller::class, ['record' => $taller->getRouteKey()])
        ->set('data.imagen', [UploadedFile::fake()->image('nueva.jpg', 800, 600)])
        ->call('save')
        ->assertHasNoFormErrors();

    Storage::disk(ImagenPublicaService::DISCO)->assertMissing('talleres/vieja.webp');
    Storage::disk(ImagenPublicaService::DISCO)->assertExists($taller->fresh()->imagen);
});

it('borra un taller sin interesados, con su imagen', function (): void {
    Storage::disk(ImagenPublicaService::DISCO)->put('talleres/rcp.webp', 'x');
    $taller = Taller::factory()->create(['imagen' => 'talleres/rcp.webp']);

    Livewire::actingAs($this->admin)
        ->test(ListTalleres::class)
        ->callTableAction('delete', $taller);

    expect(Taller::count())->toBe(0);
    Storage::disk(ImagenPublicaService::DISCO)->assertMissing('talleres/rcp.webp');
});

it('no ofrece borrar un taller con interesados', function (): void {
    $taller = Taller::factory()->create();
    SolicitudInformacion::factory()->create(['taller_id' => $taller->id]);

    Livewire::actingAs($this->admin)
        ->test(ListTalleres::class)
        ->assertTableActionHidden('delete', $taller);
});

it('reordena los talleres arrastrando las filas', function (): void {
    $primero = Taller::factory()->create(['orden' => 1]);
    $segundo = Taller::factory()->create(['orden' => 2]);

    Livewire::actingAs($this->admin)
        ->test(ListTalleres::class)
        ->call('reorderTable', [(string) $segundo->id, (string) $primero->id]);

    expect(Taller::orderBy('orden')->pluck('id')->all())->toBe([$segundo->id, $primero->id]);
});
