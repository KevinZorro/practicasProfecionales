<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra la sesión a las 24 horas de haber entrado, aunque se haya usado
 * sin parar (config laboratorio.sesion, NIST SP 800-63B). SESSION_LIFETIME
 * solo corta la inactividad, y cada petición la reinicia: sin esto, una
 * sesión usada a diario en un equipo compartido no vencería nunca.
 *
 * La hora de entrada se anota en la primera petición con sesión: entrar
 * invalida la sesión anterior, así que la marca siempre es de esta entrada,
 * sea con Google o con contraseña. Va en el grupo web, así que alcanza
 * también a /livewire/update y a /admin.
 */
final class CerrarSesionesLargas
{
    private const CLAVE = 'sesion_iniciada_en';

    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws AuthenticationException
     */
    public function handle(Request $peticion, Closure $next): Response
    {
        if (! $peticion->hasSession() || ! Auth::check()) {
            return $next($peticion);
        }

        $sesion = $peticion->session();
        $iniciadaEn = $sesion->get(self::CLAVE);

        if (! is_int($iniciadaEn)) {
            $sesion->put(self::CLAVE, now()->getTimestamp());

            return $next($peticion);
        }

        $horas = (int) config('laboratorio.sesion.duracion_maxima_horas');

        if (now()->getTimestamp() - $iniciadaEn >= $horas * 3600) {
            Auth::logout();
            $sesion->invalidate();
            $sesion->regenerateToken();
            $sesion->flash('estado', "Por seguridad, la sesión se cierra a las {$horas} horas de haber entrado. Vuelve a entrar.");

            throw new AuthenticationException('La sesión superó su duración máxima.');
        }

        return $next($peticion);
    }
}
