<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/**
 * El RF18 establece que el inicio de sesión es únicamente por cuenta de
 * Google institucional. No debe existir ninguna vía de autenticación por
 * contraseña, ni siquiera alcanzable por accidente: Breeze está instalado
 * como dependencia de desarrollo y basta con ejecutar "breeze:install" para
 * que publique todas estas rutas de golpe.
 */

/** Rutas que publicaría Breeze y que no deben responder. */
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

it('no registra ninguna ruta de contraseña en el enrutador', function (): void {
    $sospechosas = collect(Route::getRoutes())
        ->map(fn ($ruta): string => $ruta->uri())
        ->filter(fn (string $uri): bool => (bool) preg_match(
            '#(^|/)(login|register|logout|password|forgot-password|reset-password|confirm-password)(/|$)#',
            $uri,
        ));

    expect($sospechosas->values()->all())->toBe([]);
});
