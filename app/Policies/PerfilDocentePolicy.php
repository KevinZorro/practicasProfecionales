<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\PerfilDocente;
use App\Models\User;

/**
 * Perfiles docentes de la landing (RF07, RF16). Contenido público: lo gestiona
 * solo el ADMIN, sin herencia.
 *
 * A diferencia de la estructura académica, un perfil sí se borra, con sus
 * títulos (cascade) y su foto: nada lo referencia y no forma parte de ningún
 * histórico. Quitarlo de la landing sin borrarlo es desactivarlo.
 *
 * Lo que aquí no está definido lo deniega el Gate, porque las pantallas de
 * Filament heredan de RecursoDelAdmin.
 */
final class PerfilDocentePolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function view(User $usuario, PerfilDocente $perfil): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function create(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function update(User $usuario, PerfilDocente $perfil): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function delete(User $usuario, PerfilDocente $perfil): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    /** Cambiar el orden en que salen en la landing. */
    public function reorder(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }
}
