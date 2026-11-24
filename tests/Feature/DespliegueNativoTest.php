<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;

/*
 * El despliegue sin Docker (ADR 0010): .env.nativo.example y nativo.sh.
 * Lo que se rompería en silencio hasta llegar al servidor.
 */

/** @return array<string, string> */
function variablesDelEjemploNativo(): array
{
    preg_match_all('/^([A-Z_]+)=(.*)$/m', (string) file_get_contents(base_path('.env.nativo.example')), $coincidencias);

    return array_combine($coincidencias[1], $coincidencias[2]);
}

it('trae en el ejemplo nativo los valores seguros', function (): void {
    $variables = variablesDelEjemploNativo();

    expect($variables['APP_ENV'])->toBe('production')
        ->and($variables['APP_DEBUG'])->toBe('false')
        ->and($variables['SESSION_ENCRYPT'])->toBe('true')
        ->and($variables['APP_TIMEZONE'])->toBe('America/Bogota');
});

it('no depende de servicios que solo existen dentro de Docker', function (): void {
    $variables = variablesDelEjemploNativo();

    // "db", "redis" y "mailpit" son nombres del compose: fuera de él no resuelven.
    expect($variables['DB_HOST'])->not->toBe('db')
        ->and($variables['LOG_CHANNEL'])->not->toBe('stderr')
        ->and($variables['QUEUE_CONNECTION'])->toBe('database')
        ->and($variables['CACHE_STORE'])->toBe('database')
        ->and($variables['SESSION_DRIVER'])->toBe('database')
        ->and(implode("\n", $variables))->not->toContain('mailpit')
        ->and(implode("\n", $variables))->not->toContain('redis');
});

it('no trae secretos en el ejemplo nativo, solo marcas o vacíos', function (string $clave): void {
    $valor = variablesDelEjemploNativo()[$clave];

    expect($valor === '' || str_starts_with($valor, '<'))->toBeTrue("{$clave} trae un valor: {$valor}");
})->with(['APP_KEY', 'DB_PASSWORD', 'DB_DUENO_CONTRASENA', 'MAIL_PASSWORD', 'GOOGLE_CLIENT_SECRET']);

it('deja el permiso del log diario como siempre salvo que se pida (LOG_PERMISOS)', function (): void {
    expect((require base_path('config/logging.php'))['channels']['daily']['permission'])->toBeNull();

    putenv('LOG_PERMISOS=0664');
    try {
        $configuracion = require base_path('config/logging.php');
    } finally {
        putenv('LOG_PERMISOS');
    }

    // PHP-FPM (www-data) y el cron del que despliega escriben el mismo archivo.
    expect($configuracion['channels']['daily']['permission'])->toBe(0664);
});

it('trae nativo.sh ejecutable y sin errores de sintaxis', function (): void {
    expect(is_executable(base_path('nativo.sh')))->toBeTrue();

    $revision = Process::run(['bash', '-n', base_path('nativo.sh')]);

    expect($revision->successful())->toBeTrue($revision->errorOutput());
});

it('migra con el dueño y crea el usuario de la aplicación igual que el compose de producción', function (): void {
    $script = (string) file_get_contents(base_path('nativo.sh'));

    expect($script)->toContain('migrate --force')
        ->and($script)->toContain('db:seed --class=RolSeeder --force')
        ->and($script)->toContain('base:usuario-de-la-aplicacion')
        ->and($script)->toContain('DB_APLICACION_USUARIO=');
});
