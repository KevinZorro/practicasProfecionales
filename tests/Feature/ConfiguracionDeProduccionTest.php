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
