<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra en cualquier entorno que no sea local.
 *
 * Las rutas del acceso de desarrollo ya se registran solo en local, pero eso
 * por sí solo no basta: `php artisan route:cache` ejecutado en la máquina
 * del desarrollador congela las rutas tal como estaban ahí, y ese archivo
 * desplegado a producción llevaría dentro la puerta de atrás. Este
 * middleware comprueba el entorno en el momento de la petición, no en el de
 * registrar la ruta, así que la caché no puede colarla.
 */
final class SoloEnDesarrollo
{
    public function handle(Request $peticion, Closure $siguiente): Response
    {
        abort_unless(app()->environment('local'), 404);

        return $siguiente($peticion);
    }
}
