<?php

declare(strict_types=1);

use App\Filament\Resources\GaleriaFotoResource;
use App\Filament\Resources\GaleriaFotoResource\Pages\CreateGaleriaFoto;
use App\Filament\Resources\GaleriaFotoResource\Pages\EditGaleriaFoto;
use App\Filament\Resources\GaleriaFotoResource\Pages\ListGaleriaFotos;
use App\Models\GaleriaFoto;
use App\Models\User;
use App\Services\ImagenPublicaService;
use Database\Seeders\RolSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Galería de fotos de la landing (RF10), en el panel del ADMIN.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    Storage::fake(ImagenPublicaService::DISCO);
    $this->admin = User::factory()->admin()->create();
});

/** Una foto ya publicada, con su archivo en el disco. */
function fotoPublicada(int $orden = 1): GaleriaFoto
{
    $ruta = "galeria/foto-{$orden}.webp";
    Storage::disk(ImagenPublicaService::DISCO)->put($ruta, 'imagen');

    return GaleriaFoto::factory()->create(['imagen_path' => $ruta, 'orden' => $orden]);
}

// ---------------------------------------------------------------------
// La Policy
// ---------------------------------------------------------------------

it('deja al ADMIN gestionar la galería, incluido borrar y reordenar', function (): void {
    $foto = GaleriaFoto::factory()->create();

    expect($this->admin->can('viewAny', GaleriaFoto::class))->toBeTrue()
        ->and($this->admin->can('create', GaleriaFoto::class))->toBeTrue()
        ->and($this->admin->can('update', $foto))->toBeTrue()
        ->and($this->admin->can('delete', $foto))->toBeTrue()
        ->and($this->admin->can('reorder', GaleriaFoto::class))->toBeTrue();
});

it('no deja gestionar la galería a ningún otro rol', function (string $estado): void {
    $usuario = User::factory()->{$estado}()->create();
    $foto = GaleriaFoto::factory()->create();

    expect($usuario->can('viewAny', GaleriaFoto::class))->toBeFalse()
        ->and($usuario->can('create', GaleriaFoto::class))->toBeFalse()
        ->and($usuario->can('update', $foto))->toBeFalse()
        ->and($usuario->can('delete', $foto))->toBeFalse();
})->with(['coordinador', 'administrativo', 'docente', 'estudiante']);

// ---------------------------------------------------------------------
// Las pantallas
// ---------------------------------------------------------------------

it('abre el listado, el alta y la edición al ADMIN', function (): void {
    $foto = fotoPublicada();
    $this->actingAs($this->admin);

    expect(GaleriaFotoResource::getUrl('index'))->toEndWith('/admin/galeria');

    $this->get(GaleriaFotoResource::getUrl('index'))->assertOk();
    $this->get(GaleriaFotoResource::getUrl('create'))->assertOk();
    $this->get(GaleriaFotoResource::getUrl('edit', ['record' => $foto]))->assertOk();
});

it('no abre la galería a otro rol', function (): void {
    $this->actingAs(User::factory()->coordinador()->create())
        ->get(GaleriaFotoResource::getUrl('index'))
        ->assertForbidden();
});

it('sube una foto, la guarda lista para la web y la pone al final', function (): void {
    fotoPublicada(orden: 4);

    Livewire::actingAs($this->admin)
        ->test(CreateGaleriaFoto::class)
        ->fillForm([
            'titulo' => 'Estudiantes en la sala de partos',
            'imagen_path' => UploadedFile::fake()->image('foto.jpg', 3000, 2000),
            'activo' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $foto = GaleriaFoto::where('titulo', 'Estudiantes en la sala de partos')->firstOrFail();
    $guardada = getimagesizefromstring(Storage::disk(ImagenPublicaService::DISCO)->get($foto->imagen_path));

    expect($foto->imagen_path)->toStartWith('galeria/')->toEndWith('.webp')
        ->and([$guardada[0], $guardada[1], $guardada['mime']])->toBe([1600, 1067, 'image/webp'])
        ->and($foto->orden)->toBe(5);
});

it('no acepta un archivo que no es una imagen', function (): void {
    Livewire::actingAs($this->admin)
        ->test(CreateGaleriaFoto::class)
        ->fillForm([
            'titulo' => 'Documento',
            'imagen_path' => UploadedFile::fake()->create('acta.pdf', 100, 'application/pdf'),
        ])
        ->call('create')
        ->assertHasFormErrors(['imagen_path']);

    expect(GaleriaFoto::count())->toBe(0);
});

it('no acepta una imagen de más de 5 MB', function (): void {
    Livewire::actingAs($this->admin)
        ->test(CreateGaleriaFoto::class)
        ->fillForm([
            'titulo' => 'Foto sin comprimir',
            'imagen_path' => UploadedFile::fake()->image('enorme.jpg')->size(ImagenPublicaService::TAMANO_MAXIMO_KB + 1),
        ])
        ->call('create')
        ->assertHasFormErrors(['imagen_path']);

    expect(GaleriaFoto::count())->toBe(0);
});

it('muestra junto al campo el error de una imagen que no se puede publicar', function (): void {
    // Pasa la validación de tipo y tamaño (es un PNG de 45 bytes), pero su
    // cabecera dice 100 megapíxeles: la rechaza ImagenPublicaService.
    $png = UploadedFile::fake()->createWithContent('enorme.png', (string) file_get_contents(base_path('tests/Fixtures/imagenes/cabecera-de-100-megapixeles.png')));

    Livewire::actingAs($this->admin)
        ->test(CreateGaleriaFoto::class)
        ->fillForm(['titulo' => 'Panorámica', 'imagen_path' => $png])
        ->call('create')
        ->assertHasFormErrors(['imagen_path']);

    expect(GaleriaFoto::count())->toBe(0);
});

it('borra el archivo anterior al reemplazar la foto', function (): void {
    $foto = fotoPublicada();

    Livewire::actingAs($this->admin)
        ->test(EditGaleriaFoto::class, ['record' => $foto->getRouteKey()])
        // Lo que hace la pantalla: quitar la foto que había y subir otra.
        ->set('data.imagen_path', [UploadedFile::fake()->image('nueva.jpg', 800, 600)])
        ->call('save')
        ->assertHasNoFormErrors();

    $nueva = $foto->fresh()->imagen_path;

    expect($nueva)->not->toBe('galeria/foto-1.webp');
    Storage::disk(ImagenPublicaService::DISCO)->assertMissing('galeria/foto-1.webp');
    Storage::disk(ImagenPublicaService::DISCO)->assertExists($nueva);
});

it('conserva el archivo al editar solo el título', function (): void {
    $foto = fotoPublicada();

    Livewire::actingAs($this->admin)
        ->test(EditGaleriaFoto::class, ['record' => $foto->getRouteKey()])
        ->fillForm(['titulo' => 'Otro título'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($foto->fresh()->imagen_path)->toBe('galeria/foto-1.webp');
    Storage::disk(ImagenPublicaService::DISCO)->assertExists('galeria/foto-1.webp');
});

it('borra la foto y su archivo', function (): void {
    $foto = fotoPublicada();

    Livewire::actingAs($this->admin)
        ->test(ListGaleriaFotos::class)
        ->callTableAction('delete', $foto);

    expect(GaleriaFoto::count())->toBe(0);
    Storage::disk(ImagenPublicaService::DISCO)->assertMissing('galeria/foto-1.webp');
});

it('reordena la galería arrastrando las filas', function (): void {
    $primera = fotoPublicada(1);
    $segunda = fotoPublicada(2);
    $tercera = fotoPublicada(3);

    Livewire::actingAs($this->admin)
        ->test(ListGaleriaFotos::class)
        ->call('reorderTable', [(string) $tercera->id, (string) $primera->id, (string) $segunda->id]);

    expect(GaleriaFoto::orderBy('orden')->pluck('id')->all())->toBe([$tercera->id, $primera->id, $segunda->id]);
});
