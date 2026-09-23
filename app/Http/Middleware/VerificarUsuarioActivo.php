<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\AccesoService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Corta la sesión de quien dejó de tener vigencia institucional (regla 8).
 *
 * Comprobarlo solo al entrar no basta: la sincronización puede desactivar a
 * alguien que tiene la sesión abierta, y esa sesión dura lo que diga
 * SESSION_LIFETIME. Aquí se mira en cada petición. El usuario se lee de la
 * base en cada una, así que un cambio de estado vale desde la siguiente.
 *
 * Además de en el grupo de rutas del panel, está registrado como middleware
 * persistente de Livewire (AppServiceProvider): las acciones de un
 * componente ya abierto van a /livewire/update, que no pasa por las rutas
 * del panel, y sin eso quien se desactivara con una pantalla abierta podría
 * seguir pulsando botones.
 *
 * Primero cierra la sesión y después responde 403: no deja una sesión viva
 * detrás del mensaje.
 */
final class VerificarUsuarioActivo
{
    public function __construct(private readonly AccesoService $acceso) {}

    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $usuario = $peticion->user();

        if ($usuario === null || $this->acceso->puedeEntrar($usuario)) {
            return $siguiente($peticion);
        }

        Auth::logout();
        $peticion->session()->invalidate();
        $peticion->session()->regenerateToken();

        abort(403, $this->acceso->motivoDelRechazo());
    }
}
