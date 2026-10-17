<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Support\RolActivo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

/**
 * Cambio de rol activo sin cerrar sesión (RF21).
 */
final class SelectorDeRolController extends Controller
{
    public function __invoke(Request $peticion, RolActivo $rolActivo): RedirectResponse
    {
        $datos = $peticion->validate([
            'rol' => ['required', new Enum(Rol::class)],
        ]);

        $rol = Rol::from($datos['rol']);

        // Quien no tiene el rol no se lo pone, aunque forme el POST a mano.
        abort_unless($rolActivo->establecer($peticion->user(), $rol), 403);

        // A «Hoy» y no de vuelta a la pantalla en la que estaba: con otro rol
        // esa pantalla puede no existir (la bandeja de solicitudes no es del
        // docente), y volver a ella enseñaba «Prohibido» justo después de
        // decir que el cambio se hizo. «Hoy» siempre existe y enseña lo que
        // espera con el rol nuevo.
        return redirect()->route('panel.inicio')->with('estado', "Ahora estás viendo el sistema como {$rol->etiqueta()}.");
    }
}
