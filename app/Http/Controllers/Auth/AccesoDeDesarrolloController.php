<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccesoService;
use App\Support\RolActivo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Acceso de desarrollo mientras no llegan las credenciales de Google.
 *
 * La autenticación real es únicamente por Google (RF18). Este controlador
 * existe solo para poder recorrer las vistas con distintos roles antes de
 * tener las credenciales, y desaparece el día que entre Socialite: el
 * contrato con el resto del sistema es una sola línea —Auth::login()—, así
 * que sustituirlo es cambiar este archivo y su bloque de rutas, nada más.
 *
 * Está cerrado fuera de local por el middleware SoloEnDesarrollo, que
 * comprueba el entorno en cada petición.
 */
final class AccesoDeDesarrolloController extends Controller
{
    public function formulario(): View
    {
        return view('auth.acceso-de-desarrollo', [
            'usuarios' => User::query()->with('roles')->orderBy('nombre')->get(),
        ]);
    }

    public function entrar(Request $peticion, RolActivo $rolActivo, AccesoService $acceso): RedirectResponse
    {
        $datos = $peticion->validate([
            'usuario' => ['required', 'integer', 'exists:users,id'],
        ]);

        $usuario = User::findOrFail($datos['usuario']);

        // Regla 8: la misma comprobación que hará el controlador de Google.
        // Así se puede probar ya, sin esperar a las credenciales.
        if (! $acceso->puedeEntrar($usuario)) {
            return back()->withErrors(['usuario' => $acceso->motivoDelRechazo()]);
        }

        $peticion->session()->invalidate();
        $peticion->session()->regenerateToken();

        Auth::login($usuario);
        $rolActivo->olvidar();

        return redirect()->route('panel.inicio');
    }
}
