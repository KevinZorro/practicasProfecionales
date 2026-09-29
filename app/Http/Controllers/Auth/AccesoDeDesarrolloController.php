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
 * Acceso de desarrollo, para recorrer las vistas con distintos roles.
 *
 * La autenticación real es únicamente por Google (RF18), en
 * AccesoConGoogleController. Este se queda hasta probar esa entrada con las
 * credenciales reales; retirarlo es borrar este archivo, su vista y su
 * bloque de rutas, nada más.
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

        // Regla 8: la misma comprobación que hace la entrada con Google.
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
