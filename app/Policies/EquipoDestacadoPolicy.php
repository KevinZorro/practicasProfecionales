<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\EquipoDestacado;
use App\Models\User;

/**
 * Equipamiento destacado de la portada (RF01, RF10). Contenido público: lo
 * gestiona solo el ADMIN, sin herencia.
 *
 * Se borra sin problema: nada lo referencia. Quitarlo de la portada sin
 * borrarlo es desactivarlo.
 */
final class EquipoDestacadoPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function view(User $usuario, EquipoDestacado $equipo): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function create(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function update(User $usuario, EquipoDestacado $equipo): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function delete(User $usuario, EquipoDestacado $equipo): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    /** Cambiar el orden en que salen en la portada; el primero es el principal. */
    public function reorder(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }
}
