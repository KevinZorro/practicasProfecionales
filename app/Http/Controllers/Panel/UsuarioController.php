<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\AsignacionDeRol;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Reparto de roles (RF63, RF64).
 *
 * Es lo mínimo para asignar, revocar y ver quién tiene qué. La gestión
 * completa de usuarios es el RF22 y no vive aquí: los usuarios van a llegar
 * de la sincronización institucional, no se crean a mano.
 */
final class UsuarioController extends Controller
{
    public function roles(Request $peticion): View
    {
        abort_unless($peticion->user()->can('viewAny', AsignacionDeRol::class), 403);

        return view('panel.usuarios.roles');
    }
}
