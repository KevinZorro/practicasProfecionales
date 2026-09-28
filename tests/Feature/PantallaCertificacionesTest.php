<?php

declare(strict_types=1);

use App\Filament\Resources\CertificacionResource;
use App\Filament\Resources\CertificacionResource\Pages\CreateCertificacion;
use App\Filament\Resources\CertificacionResource\Pages\EditCertificacion;
use App\Filament\Resources\CertificacionResource\Pages\ListCertificaciones;
use App\Models\Certificacion;
use App\Models\User;
use App\Services\ImagenPublicaService;
use Database\Seeders\RolSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Certificaciones del laboratorio (RF06, RF15), en el panel del ADMIN.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    Storage::fake(ImagenPublicaService::DISCO);
    $this->admin = User::factory()->admin()->create();
});

it('deja al ADMIN gestionar certificaciones, incluido borrar y reordenar', function (): void {
    $certificacion = Certificacion::factory()->create();

    expect($this->admin->can('viewAny', Certificacion::class))->toBeTrue()
        ->and($this->admin->can('create', Certificacion::class))->toBeTrue()
        ->and($this->admin->can('update', $certificacion))->toBeTrue()
        ->and($this->admin->can('delete', $certificacion))->toBeTrue()
        ->and($this->admin->can('reorder', Certificacion::class))->toBeTrue();
});

it('no deja gestionar certificaciones a ningún otro rol', function (string $estado): void {
    $usuario = User::factory()->{$estado}()->create();
    $certificacion = Certificacion::factory()->create();

    expect($usuario->can('viewAny', Certificacion::class))->toBeFalse()
        ->and($usuario->can('create', Certificacion::class))->toBeFalse()
        ->and($usuario->can('update', $certificacion))->toBeFalse()
        ->and($usuario->can('delete', $certificacion))->toBeFalse();
})->with(['coordinador', 'administrativo', 'docente', 'estudiante']);

it('abre el listado, el alta y la edición al ADMIN', function (): void {
    $certificacion = Certificacion::factory()->create();
    $this->actingAs($this->admin);

    expect(CertificacionResource::getUrl('index'))->toEndWith('/admin/certificaciones');

    $this->get(CertificacionResource::getUrl('index'))->assertOk();
    $this->get(CertificacionResource::getUrl('create'))->assertOk();
    $this->get(CertificacionResource::getUrl('edit', ['record' => $certificacion]))->assertOk();
});

it('no abre las certificaciones a otro rol', function (): void {
    $this->actingAs(User::factory()->coordinador()->create())
        ->get(CertificacionResource::getUrl('index'))
        ->assertForbidden();
});

it('crea una certificación con su insignia y la pone al final', function (): void {
    Certificacion::factory()->create(['nombre' => 'Otra certificación', 'orden' => 3]);

    Livewire::actingAs($this->admin)
        ->test(CreateCertificacion::class)
        ->fillForm([
            'nombre' => 'Centro de entrenamiento certificado',
            'entidad' => 'American Heart Association',
            'imagen_insignia' => UploadedFile::fake()->image('insignia.png', 600, 600),
            'activo' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $certificacion = Certificacion::where('nombre', 'Centro de entrenamiento certificado')->firstOrFail();

    expect($certificacion->orden)->toBe(4)
        ->and($certificacion->imagen_insignia)->toStartWith('certificaciones/')->toEndWith('.webp');
    Storage::disk(ImagenPublicaService::DISCO)->assertExists($certificacion->imagen_insignia);
});

it('no crea una certificación sin nombre ni entidad', function (): void {
    Livewire::actingAs($this->admin)
        ->test(CreateCertificacion::class)
        ->fillForm(['nombre' => '', 'entidad' => ''])
        ->call('create')
        ->assertHasFormErrors(['nombre' => 'required', 'entidad' => 'required']);

    expect(Certificacion::count())->toBe(0);
});

it('borra la insignia anterior al reemplazarla', function (): void {
    Storage::disk(ImagenPublicaService::DISCO)->put('certificaciones/vieja.webp', 'x');
    $certificacion = Certificacion::factory()->create(['imagen_insignia' => 'certificaciones/vieja.webp']);

    Livewire::actingAs($this->admin)
        ->test(EditCertificacion::class, ['record' => $certificacion->getRouteKey()])
        ->set('data.imagen_insignia', [UploadedFile::fake()->image('nueva.png', 400, 400)])
        ->call('save')
        ->assertHasNoFormErrors();

    Storage::disk(ImagenPublicaService::DISCO)->assertMissing('certificaciones/vieja.webp');
});

it('borra la certificación y su insignia', function (): void {
    Storage::disk(ImagenPublicaService::DISCO)->put('certificaciones/aha.webp', 'x');
    $certificacion = Certificacion::factory()->create(['imagen_insignia' => 'certificaciones/aha.webp']);

    Livewire::actingAs($this->admin)
        ->test(ListCertificaciones::class)
        ->callTableAction('delete', $certificacion);

    expect(Certificacion::count())->toBe(0);
    Storage::disk(ImagenPublicaService::DISCO)->assertMissing('certificaciones/aha.webp');
});

it('reordena las certificaciones arrastrando las filas', function (): void {
    $primera = Certificacion::factory()->create(['orden' => 1]);
    $segunda = Certificacion::factory()->create(['orden' => 2]);

    Livewire::actingAs($this->admin)
        ->test(ListCertificaciones::class)
        ->call('reorderTable', [(string) $segunda->id, (string) $primera->id]);

    expect(Certificacion::orderBy('orden')->pluck('id')->all())->toBe([$segunda->id, $primera->id]);
});
