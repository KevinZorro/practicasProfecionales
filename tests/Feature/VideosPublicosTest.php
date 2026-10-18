<?php

declare(strict_types=1);

use App\Filament\Resources\CasoClinicoResource;
use App\Filament\Resources\CertificacionResource;
use App\Filament\Resources\EquipoDestacadoResource;
use App\Filament\Resources\EventoResource;
use App\Filament\Resources\EventoResource\Pages\EditEvento;
use App\Filament\Resources\GaleriaFotoResource;
use App\Filament\Resources\GaleriaFotoResource\Pages\CreateGaleriaFoto;
use App\Filament\Resources\GaleriaFotoResource\Pages\EditGaleriaFoto;
use App\Filament\Resources\GaleriaFotoResource\Pages\ListGaleriaFotos;
use App\Filament\Resources\PerfilDocenteResource;
use App\Filament\Resources\TallerResource;
use App\Filament\Resources\TallerResource\Pages\CreateTaller;
use App\Models\CasoClinico;
use App\Models\Evento;
use App\Models\GaleriaFoto;
use App\Models\Taller;
use App\Models\User;
use App\Services\ImagenPublicaService;
use App\Services\VideoPublicoService;
use Database\Seeders\RolSeeder;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Videos del contenido público (RF76): los sube el ADMIN como archivo y la
 * portada los muestra sin reproducirlos sola.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    Storage::fake(ImagenPublicaService::DISCO);
    $this->admin = User::factory()->admin()->create();
});

function videoPublico(string $ruta): string
{
    Storage::disk(ImagenPublicaService::DISCO)->put($ruta, 'video');

    return $ruta;
}

function disco(): Filesystem
{
    return Storage::disk(ImagenPublicaService::DISCO);
}

// ---------------------------------------------------------------------
// El ADMIN sube el video
// ---------------------------------------------------------------------

it('guarda en la galería una pieza que es solo video', function (): void {
    Livewire::actingAs($this->admin)
        ->test(CreateGaleriaFoto::class)
        ->fillForm([
            'titulo' => 'Recorrido por la sala',
            'video' => UploadedFile::fake()->create('recorrido.mp4', 4000, 'video/mp4'),
            'activo' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $pieza = GaleriaFoto::where('titulo', 'Recorrido por la sala')->firstOrFail();

    expect($pieza->imagen_path)->toBeNull()
        ->and($pieza->video)->toStartWith('galeria/')->toEndWith('.mp4');
    disco()->assertExists($pieza->video);
});

it('exige foto o video en la galería', function (): void {
    Livewire::actingAs($this->admin)
        ->test(CreateGaleriaFoto::class)
        ->fillForm(['titulo' => 'Vacía'])
        ->call('create')
        ->assertHasFormErrors(['imagen_path', 'video']);

    expect(GaleriaFoto::count())->toBe(0);
});

it('la base no deja una pieza de la galería sin foto ni video', function (): void {
    expect(fn () => GaleriaFoto::factory()->create(['imagen_path' => null, 'video' => null]))
        ->toThrow(QueryException::class);
});

it('no acepta un video por encima del tope', function (): void {
    Livewire::actingAs($this->admin)
        ->test(CreateGaleriaFoto::class)
        ->fillForm([
            'titulo' => 'Pesado',
            'video' => UploadedFile::fake()->create('pesado.mp4', VideoPublicoService::TAMANO_MAXIMO_KB + 1, 'video/mp4'),
        ])
        ->call('create')
        ->assertHasFormErrors(['video']);

    expect(GaleriaFoto::count())->toBe(0);
});

it('no acepta como video un archivo que no lo es', function (): void {
    Livewire::actingAs($this->admin)
        ->test(CreateGaleriaFoto::class)
        ->fillForm([
            'titulo' => 'Falso',
            'video' => UploadedFile::fake()->image('foto.jpg'),
        ])
        ->call('create')
        ->assertHasFormErrors(['video']);

    expect(GaleriaFoto::count())->toBe(0);
});

it('acepta WebM', function (): void {
    Livewire::actingAs($this->admin)
        ->test(CreateGaleriaFoto::class)
        ->fillForm(['titulo' => 'Web', 'video' => UploadedFile::fake()->create('web.webm', 1000, 'video/webm')])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(GaleriaFoto::firstOrFail()->video)->toEndWith('.webm');
});

it('deja elegir foto o video en talleres, eventos, escenarios y equipos, y no en certificaciones ni docentes', function (): void {
    $conVideo = [
        TallerResource::class,
        EventoResource::class,
        CasoClinicoResource::class,
        EquipoDestacadoResource::class,
        GaleriaFotoResource::class,
    ];
    $soloFoto = [CertificacionResource::class, PerfilDocenteResource::class];

    foreach ($conVideo as $recurso) {
        expect($recurso::camposDeVideo())->toBe(['video']);
    }
    foreach ($soloFoto as $recurso) {
        expect($recurso::camposDeVideo())->toBe([]);
    }
});

it('sube un video al crear un taller', function (): void {
    Livewire::actingAs($this->admin)
        ->test(CreateTaller::class)
        ->fillForm([
            'titulo' => 'Suturas',
            'descripcion' => 'Práctica de suturas',
            'tema' => 'Cirugía',
            'fecha' => now()->addWeek()->format('Y-m-d'),
            'modalidad' => 'presencial',
            'video' => UploadedFile::fake()->create('suturas.mp4', 2000, 'video/mp4'),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $taller = Taller::where('titulo', 'Suturas')->firstOrFail();
    disco()->assertExists($taller->video);
});

it('borra el video anterior al reemplazarlo', function (): void {
    $pieza = GaleriaFoto::factory()->create(['imagen_path' => null, 'video' => videoPublico('galeria/viejo.mp4')]);

    Livewire::actingAs($this->admin)
        ->test(EditGaleriaFoto::class, ['record' => $pieza->getRouteKey()])
        ->set('data.video', [UploadedFile::fake()->create('nuevo.mp4', 2000, 'video/mp4')])
        ->call('save')
        ->assertHasNoFormErrors();

    disco()->assertMissing('galeria/viejo.mp4');
    disco()->assertExists($pieza->fresh()->video);
});

it('conserva el video al editar solo el título', function (): void {
    $pieza = GaleriaFoto::factory()->create(['imagen_path' => null, 'video' => videoPublico('galeria/queda.mp4')]);

    Livewire::actingAs($this->admin)
        ->test(EditGaleriaFoto::class, ['record' => $pieza->getRouteKey()])
        ->fillForm(['titulo' => 'Otro título'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($pieza->fresh()->video)->toBe('galeria/queda.mp4');
    disco()->assertExists('galeria/queda.mp4');
});

it('borra el video de un evento al quitárselo, y la foto sigue', function (): void {
    $evento = Evento::factory()->create([
        'imagen' => archivoPublicoDeVideos('eventos/foto.webp'),
        'video' => videoPublico('eventos/viejo.mp4'),
    ]);

    Livewire::actingAs($this->admin)
        ->test(EditEvento::class, ['record' => $evento->getRouteKey()])
        ->set('data.video', [])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($evento->fresh()->video)->toBeNull();
    disco()->assertMissing('eventos/viejo.mp4');
    disco()->assertExists('eventos/foto.webp');
});

it('borra la pieza con su video', function (): void {
    $pieza = GaleriaFoto::factory()->create(['imagen_path' => archivoPublicoDeVideos('galeria/f.webp'), 'video' => videoPublico('galeria/v.mp4')]);

    Livewire::actingAs($this->admin)
        ->test(ListGaleriaFotos::class)
        ->callTableAction('delete', $pieza);

    disco()->assertMissing('galeria/v.mp4');
    disco()->assertMissing('galeria/f.webp');
});

function archivoPublicoDeVideos(string $ruta): string
{
    Storage::disk(ImagenPublicaService::DISCO)->put($ruta, 'imagen');

    return $ruta;
}

// ---------------------------------------------------------------------
// La portada
// ---------------------------------------------------------------------

it('pone el video de un taller con controles, sin reproducción automática y con la foto de portada', function (): void {
    Taller::factory()->create([
        'titulo' => 'Taller con video',
        'fecha' => now()->addWeek(),
        'imagen' => archivoPublicoDeVideos('talleres/portada.webp'),
        'video' => videoPublico('talleres/clase.mp4'),
    ]);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('talleres/clase.mp4')
        ->and($html)->toContain('poster="')->toContain('talleres/portada.webp')
        ->and($html)->toContain('preload="metadata"')
        ->and($html)->not->toContain('autoplay');
});

it('en la tarjeta del escenario el video es su primer cuadro y se reproduce en el detalle', function (): void {
    $escenario = CasoClinico::factory()->visibleEnPublico()->create(['video' => videoPublico('casos-clinicos/parto.webm')]);

    $tarjeta = $this->get('/')->assertOk()->getContent();
    $detalle = $this->get(route('portada.escenario', $escenario))->assertOk()->getContent();

    expect($tarjeta)->toContain('casos-clinicos/parto.webm')
        ->and($tarjeta)->toContain('video/webm')
        ->and($tarjeta)->not->toContain(' controls')
        ->and($detalle)->toContain('casos-clinicos/parto.webm')->toContain(' controls');
});

it('pone en la galería una pieza que es solo video', function (): void {
    GaleriaFoto::factory()->create(['titulo' => 'Recorrido', 'imagen_path' => null, 'video' => videoPublico('galeria/recorrido.mp4')]);

    $this->get('/')->assertOk()->assertSee('galeria/recorrido.mp4', false)->assertSee('Por dentro.');
});

it('trata un video cuyo archivo no está como si no hubiera video', function (): void {
    Taller::factory()->create([
        'titulo' => 'Video perdido',
        'fecha' => now()->addWeek(),
        'imagen' => archivoPublicoDeVideos('talleres/queda.webp'),
        'video' => 'talleres/borrado.mp4',
    ]);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->not->toContain('borrado.mp4')
        ->and($html)->toContain('talleres/queda.webp');
});

it('no pone en la galería una pieza cuyo único archivo falta', function (): void {
    GaleriaFoto::factory()->create(['titulo' => 'Pieza vacía', 'imagen_path' => null, 'video' => 'galeria/no-esta.mp4']);

    $this->get('/')->assertOk()->assertDontSee('no-esta.mp4', false)->assertDontSee('Pieza vacía');
});

it('muestra el video de un evento', function (): void {
    Evento::factory()->create(['titulo' => 'Jornada', 'fecha' => now()->addWeek(), 'video' => videoPublico('eventos/jornada.mp4')]);

    $this->get('/')->assertOk()->assertSee('eventos/jornada.mp4', false);
});
