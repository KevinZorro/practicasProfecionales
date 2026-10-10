<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad de todas las respuestas.
 *
 * Van en Laravel y no en nginx para que se prueben con los tests y valgan
 * igual en desarrollo y en producción. nginx solo añade las suyas a lo que
 * sirve sin pasar por PHP (los assets compilados y /storage).
 */
final class CabecerasDeSeguridad
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $peticion, Closure $next): Response
    {
        $respuesta = $next($peticion);
        $cabeceras = $respuesta->headers;

        $cabeceras->set('X-Content-Type-Options', 'nosniff');
        // Que ninguna otra página meta el panel en un iframe para engañar al
        // usuario (clickjacking). frame-ancestors de la CSP dice lo mismo
        // para los navegadores nuevos; esta, para los viejos.
        $cabeceras->set('X-Frame-Options', 'SAMEORIGIN');
        $cabeceras->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $cabeceras->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $cabeceras->set('Cross-Origin-Opener-Policy', 'same-origin');

        if (config('seguridad.politica_de_contenido') && ! Vite::isRunningHot()) {
            $cabeceras->set('Content-Security-Policy', $this->politicaDeContenido());
        }

        // Sin includeSubDomains: el dominio es de la universidad, y obligar
        // a HTTPS a subdominios que no controlamos podría dejarlos sin
        // servicio. Sobre HTTP no se manda: el navegador la ignora.
        if ($peticion->isSecure()) {
            $cabeceras->set('Strict-Transport-Security', 'max-age='.(int) config('seguridad.hsts_segundos'));
        }

        return $respuesta;
    }

    /**
     * Qué puede cargar una página, y de dónde.
     *
     * 'unsafe-inline' y 'unsafe-eval' en los scripts no son un descuido:
     * Alpine, que trae Livewire, evalúa las expresiones de las plantillas con
     * new Function(), y Livewire y Filament inyectan scripts y estilos en
     * línea. Quitarlos rompe todas las pantallas. Lo que la política sí
     * cierra: scripts y estilos de otros orígenes, objetos incrustados,
     * cambiar la base de las URL, enviar formularios a otro sitio y que otra
     * página la meta en un iframe.
     *
     * fonts.bunny.net es la tipografía de Filament. La portada no la usa:
     * sirve Onest desde el propio servidor.
     */
    private function politicaDeContenido(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            "font-src 'self' data: https://fonts.bunny.net",
            // blob: para la vista previa de lo que se va a subir.
            "img-src 'self' data: blob:",
            "media-src 'self' blob:",
            // FilePond, el campo de subida de Filament, procesa la imagen en un
            // worker que crea desde blob:.
            "worker-src 'self' blob:",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }
}
