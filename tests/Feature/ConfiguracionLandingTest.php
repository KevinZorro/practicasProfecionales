<?php

declare(strict_types=1);

use App\Enums\ClaveConfiguracionLanding as Clave;
use App\Enums\Rol;
use App\Filament\Pages\ConfiguracionDeLaLanding;
use App\Models\ConfiguracionLanding;
use App\Models\User;
use App\Services\ConfiguracionLandingService;
use App\Services\ImagenPublicaService;
use Database\Seeders\RolSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Configuración de la landing (RF02, RF11): textos del hero, su video y los
 * datos de contacto.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    Storage::fake(ImagenPublicaService::DISCO);
    $this->admin = User::factory()->admin()->create();
});

function valorDe(Clave $clave): ?string
{
    return ConfiguracionLanding::where('clave', $clave->value)->value('valor');
}

// ---------------------------------------------------------------------
// Acceso
// ---------------------------------------------------------------------

it('abre la página al ADMIN', function (): void {
    $this->actingAs($this->admin)
        ->get(ConfiguracionDeLaLanding::getUrl())
        ->assertOk()
        ->assertSee('Configuración de la landing');
});

it('solo deja editar la configuración al ADMIN', function (Rol $rol, bool $puede): void {
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);

    expect($usuario->fresh()->can('update', ConfiguracionLanding::class))->toBe($puede);
})->with([
    'admin' => [Rol::Admin, true],
    'coordinador' => [Rol::Coordinador, false],
    'administrativo' => [Rol::Administrativo, false],
    'docente' => [Rol::Docente, false],
    'estudiante' => [Rol::Estudiante, false],
]);

// ---------------------------------------------------------------------
// Textos
// ---------------------------------------------------------------------

it('muestra los valores guardados', function (): void {
    ConfiguracionLanding::create(['clave' => Clave::HeroTitulo->value, 'valor' => 'Laboratorio de Simulación']);

    Livewire::actingAs($this->admin)
        ->test(ConfiguracionDeLaLanding::class)
        ->assertFormSet([Clave::HeroTitulo->value => 'Laboratorio de Simulación']);
});

it('guarda los textos del hero y el contacto', function (): void {
    Livewire::actingAs($this->admin)
        ->test(ConfiguracionDeLaLanding::class)
        ->fillForm([
            Clave::HeroTitulo->value => 'Laboratorio de Simulación Clínica',
            Clave::HeroSubtitulo->value => 'Práctica segura',
            Clave::ContactoEmail->value => 'laboratorio@ufps.edu.co',
            Clave::ContactoTelefono->value => '+57 607 000 0000',
            Clave::ContactoDireccion->value => 'Bloque C',
        ])
        ->call('guardar')
        ->assertHasNoFormErrors();

    expect(valorDe(Clave::HeroTitulo))->toBe('Laboratorio de Simulación Clínica')
        ->and(valorDe(Clave::ContactoEmail))->toBe('laboratorio@ufps.edu.co');
});

it('actualiza una clave sin duplicarla', function (): void {
    ConfiguracionLanding::create(['clave' => Clave::HeroTitulo->value, 'valor' => 'Viejo']);

    Livewire::actingAs($this->admin)
        ->test(ConfiguracionDeLaLanding::class)
        ->fillForm([Clave::HeroTitulo->value => 'Nuevo'])
        ->call('guardar')
        ->assertHasNoFormErrors();

    expect(ConfiguracionLanding::where('clave', Clave::HeroTitulo->value)->count())->toBe(1)
        ->and(valorDe(Clave::HeroTitulo))->toBe('Nuevo');
});

it('no guarda sin título ni con un correo mal escrito', function (): void {
    Livewire::actingAs($this->admin)
        ->test(ConfiguracionDeLaLanding::class)
        ->fillForm([Clave::HeroTitulo->value => '', Clave::ContactoEmail->value => 'no-es-un-correo'])
        ->call('guardar')
        ->assertHasFormErrors([Clave::HeroTitulo->value => 'required', Clave::ContactoEmail->value => 'email']);

    expect(ConfiguracionLanding::count())->toBe(0);
});

// ---------------------------------------------------------------------
// Video del hero
// ---------------------------------------------------------------------

it('guarda el video del hero en el disco público', function (): void {
    Livewire::actingAs($this->admin)
        ->test(ConfiguracionDeLaLanding::class)
        ->fillForm([
            Clave::HeroTitulo->value => 'Laboratorio',
            Clave::HeroVideo->value => UploadedFile::fake()->create('hero.mp4', 4000, 'video/mp4'),
        ])
        ->call('guardar')
        ->assertHasNoFormErrors();

    expect(valorDe(Clave::HeroVideo))->toStartWith('landing/')->toEndWith('.mp4');
    Storage::disk(ImagenPublicaService::DISCO)->assertExists(valorDe(Clave::HeroVideo));
});

it('no acepta un video por encima del tope', function (): void {
    Livewire::actingAs($this->admin)
        ->test(ConfiguracionDeLaLanding::class)
        ->fillForm([
            Clave::HeroTitulo->value => 'Laboratorio',
            Clave::HeroVideo->value => UploadedFile::fake()->create('hero.mp4', ConfiguracionLandingService::TAMANO_MAXIMO_VIDEO_KB + 1, 'video/mp4'),
        ])
        ->call('guardar')
        ->assertHasFormErrors([Clave::HeroVideo->value]);

    expect(valorDe(Clave::HeroVideo))->toBeNull();
});

it('no acepta como video un archivo que no lo es', function (): void {
    Livewire::actingAs($this->admin)
        ->test(ConfiguracionDeLaLanding::class)
        ->fillForm([
            Clave::HeroTitulo->value => 'Laboratorio',
            Clave::HeroVideo->value => UploadedFile::fake()->image('portada.jpg'),
        ])
        ->call('guardar')
        ->assertHasFormErrors([Clave::HeroVideo->value]);
});

it('borra el video anterior al reemplazarlo', function (): void {
    Storage::disk(ImagenPublicaService::DISCO)->put('landing/viejo.mp4', 'x');
    ConfiguracionLanding::create(['clave' => Clave::HeroTitulo->value, 'valor' => 'Laboratorio']);
    ConfiguracionLanding::create(['clave' => Clave::HeroVideo->value, 'valor' => 'landing/viejo.mp4']);

    Livewire::actingAs($this->admin)
        ->test(ConfiguracionDeLaLanding::class)
        ->set('datos.'.Clave::HeroVideo->value, [UploadedFile::fake()->create('nuevo.mp4', 2000, 'video/mp4')])
        ->call('guardar')
        ->assertHasNoFormErrors();

    expect(valorDe(Clave::HeroVideo))->not->toBe('landing/viejo.mp4');
    Storage::disk(ImagenPublicaService::DISCO)->assertMissing('landing/viejo.mp4');
});

it('borra el video al quitarlo', function (): void {
    Storage::disk(ImagenPublicaService::DISCO)->put('landing/viejo.mp4', 'x');
    ConfiguracionLanding::create(['clave' => Clave::HeroTitulo->value, 'valor' => 'Laboratorio']);
    ConfiguracionLanding::create(['clave' => Clave::HeroVideo->value, 'valor' => 'landing/viejo.mp4']);

    Livewire::actingAs($this->admin)
        ->test(ConfiguracionDeLaLanding::class)
        // Lo que deja la pantalla al quitar el archivo.
        ->set('datos.'.Clave::HeroVideo->value, [])
        ->call('guardar')
        ->assertHasNoFormErrors();

    expect(valorDe(Clave::HeroVideo))->toBeNull();
    Storage::disk(ImagenPublicaService::DISCO)->assertMissing('landing/viejo.mp4');
});

it('conserva el video si al guardar no se tocó', function (): void {
    Storage::disk(ImagenPublicaService::DISCO)->put('landing/hero.mp4', 'x');
    ConfiguracionLanding::create(['clave' => Clave::HeroVideo->value, 'valor' => 'landing/hero.mp4']);

    app(ConfiguracionLandingService::class)->guardar([Clave::HeroTitulo->value => 'Otro título']);

    expect(valorDe(Clave::HeroVideo))->toBe('landing/hero.mp4');
    Storage::disk(ImagenPublicaService::DISCO)->assertExists('landing/hero.mp4');
});

// ---------------------------------------------------------------------
// El tope del video tiene que caber en toda la cadena de subida
// ---------------------------------------------------------------------

/** Convierte "20M", "24M" o "512K" a KB. */
function aKilobytes(string $tamano): int
{
    $numero = (int) $tamano;

    return match (strtoupper(substr(trim($tamano), -1))) {
        'G' => $numero * 1024 * 1024,
        'M' => $numero * 1024,
        default => $numero,
    };
}

it('deja subir el video del hero por PHP, nginx y Livewire', function (): void {
    // Si alguno se queda corto, la subida se corta antes de llegar al
    // formulario, con un error que no dice por qué. Livewire tiene que
    // quedar por encima del tope, para que el mensaje lo dé el formulario.
    $tope = ConfiguracionLandingService::TAMANO_MAXIMO_VIDEO_KB;
    preg_match('/max:(\d+)/', implode('|', config('livewire.temporary_file_upload.rules')), $livewire);

    $php = (string) file_get_contents(base_path('docker/php/php.ini'));
    preg_match('/^upload_max_filesize\s*=\s*(\S+)/m', $php, $subida);
    preg_match('/^post_max_size\s*=\s*(\S+)/m', $php, $peticion);

    $nginx = (string) file_get_contents(base_path('docker/nginx/default.conf'));
    preg_match('/client_max_body_size\s+(\S+);/', $nginx, $cuerpo);

    expect(aKilobytes($subida[1]))->toBeGreaterThanOrEqual($tope)
        ->and(aKilobytes($peticion[1]))->toBeGreaterThanOrEqual($tope)
        ->and(aKilobytes($cuerpo[1]))->toBeGreaterThanOrEqual((int) $livewire[1])
        ->and(aKilobytes($subida[1]))->toBeGreaterThanOrEqual((int) $livewire[1])
        ->and((int) $livewire[1])->toBeGreaterThan($tope);
});

it('sirve los archivos públicos con nosniff', function (): void {
    // El video no se recodifica al subirlo: que el navegador no adivine su tipo.
    $nginx = (string) file_get_contents(base_path('docker/nginx/default.conf'));
    preg_match('/location \^~ \/storage\/ \{(.*?)\}/s', $nginx, $bloque);

    expect($bloque[1] ?? '')->toContain('X-Content-Type-Options "nosniff"');
});
