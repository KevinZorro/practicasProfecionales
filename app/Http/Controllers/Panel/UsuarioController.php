<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\AsignacionDeRol;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Usuarios y roles: gestión de cuentas (RF22) y reparto de roles (RF63,
 * RF64). Solo el ADMIN. La mayoría de cuentas llegan de la sincronización
 * institucional (RF20); aquí se crean las que no figuran en ella.
 */
final class UsuarioController extends Controller
{
    public function roles(Request $peticion): View
    {
        abort_unless($peticion->user()->can('viewAny', AsignacionDeRol::class), 403);

        return view('panel.usuarios.roles');
    }

    public function nueva(Request $peticion): View
    {
        abort_unless($peticion->user()->can('create', User::class), 403);

        return view('panel.usuarios.formulario', ['cuenta' => null]);
    }

    public function editar(Request $peticion, User $cuenta): View
    {
        abort_unless($peticion->user()->can('update', $cuenta), 403);

        return view('panel.usuarios.formulario', ['cuenta' => $cuenta]);
    }
}
