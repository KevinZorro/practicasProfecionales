<?php

use App\Http\Middleware\EstablecerRolActivo;
use App\Http\Middleware\SoloEnDesarrollo;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'rol.activo' => EstablecerRolActivo::class,
            'solo.desarrollo' => SoloEnDesarrollo::class,
        ]);

        // La entrada real será por Google (RF18); mientras tanto el único
        // punto de acceso es el de desarrollo, que solo existe en local.
        $middleware->redirectGuestsTo(
            static fn (): string => Route::has('login') ? route('login') : '/',
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
