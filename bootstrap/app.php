<?php

use App\Http\Middleware\EstablecerRolActivo;
use App\Http\Middleware\SoloEnDesarrollo;
use App\Http\Middleware\VerificarUsuarioActivo;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
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
    // Sin descubrimiento automático de listeners: AppServiceProvider los
    // registra a mano para que el enlace evento-listener se lea de un
    // vistazo. Con los dos a la vez cada listener quedaba registrado dos
    // veces y el docente recibía dos correos por cada aprobación (RF33).
    ->withEvents(discover: false)
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'rol.activo' => EstablecerRolActivo::class,
            'solo.desarrollo' => SoloEnDesarrollo::class,
            'usuario.activo' => VerificarUsuarioActivo::class,
        ]);

        // Laravel reordena los middleware de la lista de prioridad, y los de
        // autenticación van en ella: el Authenticate de Filament, que
        // pregunta canAccessPanel(), acabaría delante de los nuestros y
        // decidiría con todos los roles del usuario en vez de con el activo.
        // Declararlos aquí, antes que AuthenticatesRequests, fija el orden en
        // cualquier pila de rutas, incluida la que Livewire vuelve a aplicar
        // en /livewire/update. Con un invitado no hacen nada: sin usuario,
        // los dos dejan pasar y decide la autenticación.
        $middleware->prependToPriorityList(AuthenticatesRequests::class, VerificarUsuarioActivo::class);
        $middleware->prependToPriorityList(AuthenticatesRequests::class, EstablecerRolActivo::class);

        // La entrada real será por Google (RF18); mientras tanto el único
        // punto de acceso es el de desarrollo, que solo existe en local.
        $middleware->redirectGuestsTo(
            static fn (): string => Route::has('login') ? route('login') : '/',
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
