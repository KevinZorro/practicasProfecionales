<?php

use App\Http\Middleware\CabecerasDeSeguridad;
use App\Http\Middleware\EstablecerRolActivo;
use App\Http\Middleware\SoloEnDesarrollo;
use App\Http\Middleware\VerificarUsuarioActivo;
use App\Services\AccesoService;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        // En todas las respuestas, también las de error y las de Filament.
        $middleware->append(CabecerasDeSeguridad::class);

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

        // El invitado va a la entrada con Google (RF18) si hay credenciales;
        // si no, al acceso de desarrollo, que solo existe en local, y en
        // cualquier otro caso a la portada.
        $middleware->redirectGuestsTo(static function (): string {
            if (app(AccesoService::class)->entradaConGoogleDisponible()) {
                return route('acceso');
            }

            return Route::has('login') ? route('login') : '/';
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Una regla de dominio rota por una petición (un segundo toque, una
        // URL escrita a mano, una acción llamada desde la consola) es un
        // «no se puede» con su mensaje, no un fallo del servidor (QA-04).
        // PostgreSQL rechaza un valor por su tipo (22P02 bigint o enum mal
        // escrito, 22007/22008 fecha inválida): lo escrito a mano en una URL
        // no existe, y no es un fallo del servidor (QA-17).
        $exceptions->render(static function (QueryException $consulta) {
            if (in_array($consulta->getCode(), ['22P02', '22007', '22008'], true)) {
                throw new NotFoundHttpException(previous: $consulta);
            }

            return null;
        });
        $exceptions->map(DomainException::class, static fn (DomainException $regla): HttpException => new HttpException(422, $regla->getMessage(), $regla));
    })->create();
