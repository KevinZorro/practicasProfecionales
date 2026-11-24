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
            $this->tomarLaDireccionEnLaPeticion($peticion, $direccion);
        }

        return $next($peticion);
    }

    /**
     * forceRootUrl alcanza a route() y asset(), pero no a lo que Laravel
     * arma con la URL de la propia petición: la página a la que se vuelve
     * después de entrar (url.intended) y la de «volver atrás» (back()).
     * Esas salían con el Host recortado —http://localhost/panel, sin el
     * :8080 del túnel— y el navegador no encontraba el servidor. Se le
     * pone a la petición el esquema, el nombre y el puerto de APP_URL.
     */
    private function tomarLaDireccionEnLaPeticion(Request $peticion, string $direccion): void
    {
        $partes = parse_url($direccion);
        $host = $partes['host'] ?? null;

        if ($host === null) {
            return;
        }

        $esHttps = ($partes['scheme'] ?? 'http') === 'https';
        $puerto = $partes['port'] ?? ($esHttps ? 443 : 80);
        $hostConPuerto = isset($partes['port']) ? $host.':'.$puerto : $host;

        $peticion->headers->set('HOST', $hostConPuerto);
        $peticion->server->set('HTTP_HOST', $hostConPuerto);
        $peticion->server->set('SERVER_NAME', $host);
        $peticion->server->set('SERVER_PORT', (string) $puerto);
        $peticion->server->set('HTTPS', $esHttps ? 'on' : 'off');
    }
}
