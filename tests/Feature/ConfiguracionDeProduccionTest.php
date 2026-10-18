<?php

declare(strict_types=1);

/*
 * Los archivos de producción: lo que no se puede perder sin que se note
 * hasta que ya está en el servidor. La CI construye las imágenes y levanta
 * el compose de producción; estos tests vigilan lo que ella no ve.
 */

function archivoDeProduccion(string $ruta): string
{
    return (string) file_get_contents(base_path($ruta));
}

/** @return array<string, string> */
function variablesDelEjemplo(): array
{
    preg_match_all('/^([A-Z_]+)=(.*)$/m', archivoDeProduccion('.env.produccion.example'), $coincidencias);

    return array_combine($coincidencias[1], $coincidencias[2]);
}

it('no enseña los errores ni la versión de PHP en producción', function (): void {
    $php = archivoDeProduccion('docker/php/php-produccion.ini');

    expect($php)->toMatch('/^display_errors\s*=\s*Off$/m')
        ->toMatch('/^display_startup_errors\s*=\s*Off$/m')
        ->toMatch('/^log_errors\s*=\s*On$/m')
        ->toMatch('/^expose_php\s*=\s*Off$/m');
});

it('no revisa en cada petición si el código cambió dentro de la imagen', function (): void {
    expect(archivoDeProduccion('docker/php/php-produccion.ini'))->toMatch('/^opcache\.validate_timestamps\s*=\s*0$/m');
});

it('no dice qué versión de nginx corre ni deja que PHP lo diga', function (): void {
    expect(archivoDeProduccion('docker/produccion/nginx.conf'))
        ->toContain('server_tokens off;')
        ->toContain('fastcgi_hide_header X-Powered-By;')
        ->not->toContain('autoindex on');
});

it('trae en el ejemplo de producción los valores seguros', function (): void {
    $variables = variablesDelEjemplo();

    expect($variables['APP_ENV'])->toBe('production')
        ->and($variables['APP_DEBUG'])->toBe('false')
        ->and($variables['SESSION_SECURE_COOKIE'])->toBe('true')
        ->and($variables['LOG_CHANNEL'])->toBe('stderr')
        ->and($variables['POLITICA_DE_CONTENIDO'])->toBe('true');
});

it('no trae secretos en el ejemplo de producción, solo marcas para llenarlos', function (): void {
    $variables = variablesDelEjemplo();

    expect($variables['APP_KEY'])->toStartWith('<')
        ->and($variables['DB_PASSWORD'])->toStartWith('<')
        ->and($variables['MAIL_PASSWORD'])->toStartWith('<')
        ->and($variables['GOOGLE_CLIENT_SECRET'])->toBe('');
});

it('trae en el ejemplo todas las variables que el compose de producción exige', function (): void {
    // "${VARIABLE:?mensaje}" hace fallar el compose si falta: el ejemplo
    // tiene que traerlas todas para que copiarlo sea suficiente.
    preg_match_all('/\$\{([A-Z_]+):\?/', archivoDeProduccion('docker-compose.produccion.yml'), $exigidas);

    expect(array_diff(array_unique($exigidas[1]), array_keys(variablesDelEjemplo())))->toBe([]);
});

it('corre la aplicación de producción sin root', function (): void {
    $dockerfile = archivoDeProduccion('docker/php/Dockerfile');
    preg_match('/^FROM base AS produccion$(.*?)^FROM /ms', $dockerfile, $etapa);

    expect($etapa[1] ?? '')->toMatch('/^USER www-data$/m');
});

it('deja ejecutables los scripts de arranque y de copias', function (string $script): void {
    expect(is_executable(base_path($script)))->toBeTrue($script);
})->with([
    'docker/produccion/arrancar.sh',
    'docker/produccion/copias/programar.sh',
    'docker/produccion/copias/hacer-copia.sh',
    'docker/produccion/copias/restaurar-copia.sh',
]);

it('guarda en la copia la base, las imágenes y los documentos privados', function (): void {
    // El formato de confidencialidad firmado vive en storage/app/private: si
    // la copia no lo incluyera, se perdería justo lo que no se puede volver
    // a pedir.
    $copia = archivoDeProduccion('docker/produccion/copias/hacer-copia.sh');

    expect($copia)->toContain('pg_dump')
        ->toContain('/datos/archivos-publicos')
        ->toContain('/datos/archivos-privados')
        ->toContain('SHA256SUMS');
});

it('no mete en las imágenes lo que suben los usuarios ni las copias', function (): void {
    // Una imagen construida en un equipo de desarrollo se llevaría dentro
    // los formatos de confidencialidad de prueba, con datos personales.
    $ignorado = archivoDeProduccion('.dockerignore');

    expect($ignorado)->toMatch('#^storage/app/private/\*$#m')
        ->toMatch('#^storage/app/public/\*$#m')
        ->toMatch('#^\.env$#m')
        ->toMatch('#^copias$#m');
});

// ---------------------------------------------------------------------
// QA-13: conexiones lentas (RNF10)
// ---------------------------------------------------------------------

it('comprime en nginx el texto que más pesa: JavaScript, CSS, JSON y SVG', function (): void {
    $nginx = archivoDeProduccion('docker/produccion/nginx.conf');

    expect($nginx)->toMatch('/^\s*gzip\s+on;$/m');

    preg_match('/^\s*gzip_types\s+([^;]+);$/m', $nginx, $tipos);
    $tipos = preg_split('/\s+/', trim($tipos[1] ?? '')) ?: [];

    expect($tipos)->toContain('text/css', 'application/javascript', 'application/json', 'image/svg+xml')
        ->not->toContain('image/webp', 'video/mp4', 'application/pdf');
});

it('no descarga JavaScript que nadie usa', function (): void {
    $paquete = json_decode(archivoDeProduccion('package.json'), true, flags: JSON_THROW_ON_ERROR);
    $dependencias = array_merge($paquete['dependencies'] ?? [], $paquete['devDependencies'] ?? []);

    // axios solo se cargaba en window y nadie lo llamaba; concurrently no
    // está en ningún script.
    expect($dependencias)->not->toHaveKeys(['axios', 'concurrently'])
        ->and(base_path('resources/js/bootstrap.js'))->not->toBeFile()
        ->and(archivoDeProduccion('vite.config.js'))->not->toContain('resources/js/app.js');

    foreach (['components/pantalla-de-acceso', 'components/layouts/app', 'layouts/panel'] as $vista) {
        expect(archivoDeProduccion("resources/views/{$vista}.blade.php"))->not->toContain('resources/js/app.js');
    }
});

// ---------------------------------------------------------------------
// QA-14: disponibilidad (RNF02)
// ---------------------------------------------------------------------

it('vigila con un healthcheck la aplicación y el servidor web, además de la base', function (): void {
    $compose = archivoDeProduccion('docker-compose.produccion.yml');

    foreach (['app', 'web', 'db'] as $servicio) {
        preg_match("/\n  {$servicio}:\n((?:    .*\n|\n)+)/", $compose, $bloque);

        expect($bloque[1] ?? '')->toContain('healthcheck:');
    }

    expect($compose)->toContain('http://127.0.0.1/up');
});

it('rota los registros de Docker de todos los servicios que corren', function (): void {
    $compose = archivoDeProduccion('docker-compose.produccion.yml');

    expect($compose)->toContain('x-registros: &registros')
        ->and($compose)->toMatch('/max-size: "10m"/')
        ->and($compose)->toMatch('/max-file: "5"/');

    foreach (['app', 'queue', 'programador', 'web', 'db', 'copias'] as $servicio) {
        preg_match("/\n  {$servicio}:\n((?:    .*\n|\n)+)/", $compose, $bloque);

        expect($bloque[1] ?? '')->toContain('logging: *registros');
    }
});
