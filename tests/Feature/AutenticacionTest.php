<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/**
 * El RF18 establece que el inicio de sesión es únicamente por cuenta de
 * Google institucional. No debe existir ninguna vía de autenticación por
 * contraseña. Breeze ya se retiró del proyecto y la columna users.password
 * se eliminó, pero estos tests se quedan como red de regresión: cualquier
 * paquete de scaffolding publica estas rutas de golpe.
 */

/** Rutas típicas de autenticación por contraseña que no deben responder. */
dataset('rutas de contraseña', [
    'formulario de ingreso' => ['get', '/login'],
    'envío de credenciales' => ['post', '/login'],
    'formulario de registro' => ['get', '/register'],
    'envío de registro' => ['post', '/register'],
    'solicitud de recuperación' => ['get', '/forgot-password'],
    'envío de recuperación' => ['post', '/forgot-password'],
    'formulario de restablecimiento' => ['get', '/reset-password/un-token-cualquiera'],
    'envío de restablecimiento' => ['post', '/reset-password'],
    'confirmación de contraseña' => ['get', '/confirm-password'],
    'envío de confirmación' => ['post', '/confirm-password'],
    'actualización de contraseña' => ['put', '/password'],
]);

it('no responde en ninguna ruta de autenticación por contraseña', function (string $metodo, string $ruta): void {
    $this->{$metodo}($ruta)->assertNotFound();
})->with('rutas de contraseña');

/**
 * Rutas que coinciden con el patrón y no tienen nada que ver con contraseñas.
 * Van por nombre exacto: un paquete que publique otra ruta de salida tiene
 * que volver a pasar por aquí.
 *
 * @var array<string, string>
 */
const RUTAS_PERMITIDAS_POR_NOMBRE = [
    // Filament la registra siempre, tenga o no ->login(). Solo cierra la
    // sesión, y su menú apunta de todas formas a nuestra ruta "salir".
    'filament.admin.auth.logout' => 'admin/logout',
];

it('no registra ninguna ruta de contraseña en el enrutador', function (): void {
    $sospechosas = collect(Route::getRoutes())
        ->reject(fn ($ruta): bool => array_key_exists((string) $ruta->getName(), RUTAS_PERMITIDAS_POR_NOMBRE))
        ->map(fn ($ruta): string => $ruta->uri())
        ->filter(fn (string $uri): bool => (bool) preg_match(
            '#(^|/)(login|register|logout|password|forgot-password|reset-password|confirm-password)(/|$)#',
            $uri,
        ));

    expect($sospechosas->values()->all())->toBe([]);
});

it('solo exceptúa rutas que existen con el nombre y la dirección anotados', function (): void {
    // Si Filament cambia el nombre o la dirección de su ruta de salida, la
    // excepción deja de cubrirla y tiene que revisarse, no quedarse muerta.
    foreach (RUTAS_PERMITIDAS_POR_NOMBRE as $nombre => $uri) {
        expect(Route::has($nombre))->toBeTrue("No existe la ruta {$nombre}.")
            ->and(Route::getRoutes()->getByName($nombre)?->uri())->toBe($uri);
    }
});
