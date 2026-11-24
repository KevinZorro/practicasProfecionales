<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Arma los enlaces con APP_URL y no con la cabecera Host de la petición.
 *
 * Por defecto Laravel copia el Host que llega: si algo en el camino lo
 * cambia —un túnel SSH a localhost:8080 detrás de un proxy que reenvía
 * `Host: $host`, sin el puerto—, la página pide sus estilos, scripts e
 * imágenes a http://localhost, otro origen, y la CSP los bloquea. Mientras
 * falte la IP del proxy de la universidad (ADR 0006) pasaría lo mismo con
 * el esquema: enlaces http:// en una página servida por https://.
 *
 * Se activa con APP_URL_FIJA, que en producción vale true por defecto.
 * En desarrollo queda apagada para poder entrar por cualquier puerto.
 */
final class UsarLaDireccionConfigurada
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $peticion, Closure $next): Response
    {
        $direccion = rtrim((string) config('app.url'), '/');

        if (config('app.url_fija') && $direccion !== '') {
            URL::forceRootUrl($direccion);
            URL::forceScheme(str_starts_with($direccion, 'https://') ? 'https' : 'http');
        }

        return $next($peticion);
    }
}
