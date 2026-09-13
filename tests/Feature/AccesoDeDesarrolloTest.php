<?php

declare(strict_types=1);

use App\Http\Middleware\SoloEnDesarrollo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

it('no registra la ruta de acceso de desarrollo fuera de local', function (): void {
    // La suite corre con APP_ENV=testing, así que aquí ya no debería existir.
    expect(app()->environment())->not->toBe('local')
        ->and(Route::has('login'))->toBeFalse()
        ->and(Route::has('desarrollo.entrar'))->toBeFalse();
});

it('devuelve 404 en la url del acceso de desarrollo fuera de local', function (string $metodo): void {
    $this->call($metodo, '/desarrollo/acceso')->assertNotFound();
})->with(['GET', 'POST']);

it('cierra el acceso de desarrollo aunque la ruta llegue por una caché de rutas', function (): void {
    // Escenario real: route:cache generado en la máquina del desarrollador y
    // desplegado a producción. La ruta existiría; el middleware la cierra
    // igual porque mira el entorno en el momento de la petición.
    $middleware = new SoloEnDesarrollo;

    expect(fn () => $middleware->handle(Request::create('/desarrollo/acceso'), fn () => response('dentro')))
        ->toThrow(NotFoundHttpException::class);
});

it('deja pasar el acceso de desarrollo solo cuando el entorno es local', function (): void {
    app()->detectEnvironment(static fn (): string => 'local');

    $respuesta = (new SoloEnDesarrollo)->handle(
        Request::create('/desarrollo/acceso'),
        static fn () => response('dentro'),
    );

    expect($respuesta->getContent())->toBe('dentro');
});

it('vuelve a cerrarse en cuanto el entorno deja de ser local', function (string $entorno): void {
    app()->detectEnvironment(static fn (): string => $entorno);

    expect(fn () => (new SoloEnDesarrollo)->handle(Request::create('/x'), fn () => response('dentro')))
        ->toThrow(NotFoundHttpException::class);
})->with(['production', 'staging', 'testing', 'Local', 'LOCAL']);
