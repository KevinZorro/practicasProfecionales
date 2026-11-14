<?php

declare(strict_types=1);

use App\Filament\Resources\VideoInstitucionalResource;
use App\Filament\Resources\VideoInstitucionalResource\Pages\CreateVideoInstitucional;
use App\Filament\Resources\VideoInstitucionalResource\Pages\EditVideoInstitucional;
use App\Filament\Resources\VideoInstitucionalResource\Pages\ListVideosInstitucionales;
use App\Models\User;
use App\Models\VideoInstitucional;
use App\Services\ImagenPublicaService;
use App\Services\VideoPublicoService;
use Database\Seeders\RolSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Galería de videos institucionales (RF08, RF17): el ADMIN sube cada video
 * al servidor con su título, su portada y su orden, y la portada pública no
 * descarga ningún video hasta que el visitante lo reproduce.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    Storage::fake(ImagenPublicaService::DISCO);
    $this->admin = User::factory()->admin()->create();
});

/** Un archivo ya guardado en el disco público, como lo dejaría la subida. */
function archivoDeLaGaleriaDeVideos(string $ruta): string
{
    Storage::disk(ImagenPublicaService::DISCO)->put($ruta, 'contenido');

    return $ruta;
}

// ---------------------------------------------------------------------
// /admin
// ---------------------------------------------------------------------

it('el ADMIN sube un video con su portada, que entra al final del orden', function (): void {
    VideoInstitucional::factory()->create(['orden' => 4]);

    Livewire::actingAs($this->admin)
        ->test(CreateVideoInstitucional::class)
        ->fillForm([
            'titulo' => 'Recorrido por el laboratorio',
            'video' => UploadedFile::fake()->create('recorrido.mp4', 4000, 'video/mp4'),
            'portada' => UploadedFile::fake()->image('portada.jpg', 1920, 1080),
            'activo' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $video = VideoInstitucional::where('titulo', 'Recorrido por el laboratorio')->firstOrFail();

    expect($video->orden)->toBe(5)
        ->and($video->video)->toStartWith('videos/')->toEndWith('.mp4')
        ->and($video->portada)->toStartWith('videos/')->toEndWith('.webp');
    Storage::disk(ImagenPublicaService::DISCO)->assertExists([$video->video, $video->portada]);
});

it('exige el video, la portada y el título', function (): void {
    Livewire::actingAs($this->admin)
        ->test(CreateVideoInstitucional::class)
        ->fillForm(['titulo' => '', 'activo' => true])
        ->call('create')
        ->assertHasFormErrors(['titulo' => 'required', 'video' => 'required', 'portada' => 'required']);

    expect(VideoInstitucional::count())->toBe(0);
});

it('rechaza un video de otro tipo o más grande que el tope', function (UploadedFile $archivo): void {
    Livewire::actingAs($this->admin)
        ->test(CreateVideoInstitucional::class)
        ->fillForm([
            'titulo' => 'Fuera de norma',
            'video' => $archivo,
            'portada' => UploadedFile::fake()->image('portada.jpg'),
        ])
        ->call('create')
        ->assertHasFormErrors(['video']);

    expect(VideoInstitucional::count())->toBe(0);
})->with([
    'AVI' => fn () => UploadedFile::fake()->create('clase.avi', 1000, 'video/x-msvideo'),
    'más del tope' => fn () => UploadedFile::fake()->create('largo.mp4', VideoPublicoService::TAMANO_MAXIMO_KB + 1, 'video/mp4'),
]);

it('al reemplazar el video borra el archivo anterior', function (): void {
    $video = VideoInstitucional::factory()->create([
        'video' => archivoDeLaGaleriaDeVideos('videos/viejo.mp4'),
        'portada' => archivoDeLaGaleriaDeVideos('videos/portada.webp'),
    ]);

    Livewire::actingAs($this->admin)
        ->test(EditVideoInstitucional::class, ['record' => $video->getRouteKey()])
        ->set('data.video', [UploadedFile::fake()->create('nuevo.mp4', 2000, 'video/mp4')])
        ->call('save')
        ->assertHasNoFormErrors();

    Storage::disk(ImagenPublicaService::DISCO)->assertMissing('videos/viejo.mp4');
    Storage::disk(ImagenPublicaService::DISCO)->assertExists($video->fresh()->video);
});

it('al borrar un video borra su archivo y su portada', function (): void {
    $video = VideoInstitucional::factory()->create([
        'video' => archivoDeLaGaleriaDeVideos('videos/clase.mp4'),
        'portada' => archivoDeLaGaleriaDeVideos('videos/clase.webp'),
    ]);

    Livewire::actingAs($this->admin)
        ->test(ListVideosInstitucionales::class)
        ->callTableAction('delete', $video);

    expect(VideoInstitucional::count())->toBe(0);
    Storage::disk(ImagenPublicaService::DISCO)->assertMissing(['videos/clase.mp4', 'videos/clase.webp']);
});

it('solo el ADMIN entra a los videos institucionales', function (string $rol): void {
    $this->actingAs(User::factory()->{$rol}()->create())
        ->get(VideoInstitucionalResource::getUrl('index'))
        ->assertForbidden();
})->with(['coordinador', 'administrativo', 'docente', 'estudiante']);

// ---------------------------------------------------------------------
// La portada
// ---------------------------------------------------------------------

it('la portada muestra los videos publicados en su orden, con título y controles', function (): void {
    VideoInstitucional::factory()->create([
        'titulo' => 'Segundo', 'orden' => 2,
        'video' => archivoDeLaGaleriaDeVideos('videos/b.mp4'), 'portada' => archivoDeLaGaleriaDeVideos('videos/b.webp'),
    ]);
    VideoInstitucional::factory()->create([
        'titulo' => 'Primero', 'orden' => 1,
        'video' => archivoDeLaGaleriaDeVideos('videos/a.mp4'), 'portada' => archivoDeLaGaleriaDeVideos('videos/a.webp'),
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('En movimiento.')
        ->assertSeeInOrder(['Primero', 'Segundo'])
        ->assertSee('videos/a.mp4', false)
        ->assertSee(' controls', false);
});

it('con portada no descarga nada del video hasta que se reproduce (RF17)', function (): void {
    VideoInstitucional::factory()->create([
        'video' => archivoDeLaGaleriaDeVideos('videos/recorrido.mp4'),
        'portada' => archivoDeLaGaleriaDeVideos('videos/recorrido.webp'),
    ]);

    $html = $this->get('/')->assertOk()->getContent();
    $seccion = substr($html, (int) strpos($html, 'id="videos"'));

    expect($seccion)->toContain('preload="none"')
        ->toContain('poster="')->toContain('videos/recorrido.webp')
        ->not->toContain('autoplay');
});

it('sin la portada en el disco, el video muestra su primer cuadro', function (): void {
    VideoInstitucional::factory()->create([
        'video' => archivoDeLaGaleriaDeVideos('videos/recorrido.mp4'),
        'portada' => 'videos/perdida.webp',
    ]);

    $html = $this->get('/')->assertOk()->getContent();
    $seccion = substr($html, (int) strpos($html, 'id="videos"'));

    expect($seccion)->toContain('preload="metadata"')
        ->not->toContain('videos/perdida.webp');
});

it('no pinta un video sin publicar ni uno cuyo archivo falta', function (): void {
    VideoInstitucional::factory()->create([
        'titulo' => 'Borrador', 'activo' => false,
        'video' => archivoDeLaGaleriaDeVideos('videos/borrador.mp4'), 'portada' => archivoDeLaGaleriaDeVideos('videos/borrador.webp'),
    ]);
    VideoInstitucional::factory()->create(['titulo' => 'Perdido', 'video' => 'videos/perdido.mp4']);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('En movimiento.')
        ->assertDontSee('Borrador')
        ->assertDontSee('Perdido');
});
