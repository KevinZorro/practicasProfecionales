<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\User;

/**
 * Gestión de cuentas (RF22): crear, editar, deshabilitar y habilitar. Solo
 * el ADMIN. Los roles van aparte, por AsignacionDeRolPolicy.
 */
final class UserPolicy
{
    public function create(User $usuario): bool
    {
        return $this->administraLaPlataforma($usuario);
    }

    public function update(User $usuario, User $cuenta): bool
    {
        return $this->administraLaPlataforma($usuario);
    }

    /** Nadie se deshabilita a sí mismo: dejaría la plataforma sin ADMIN por un clic. */
    public function deshabilitar(User $usuario, User $cuenta): bool
    {
        return $this->administraLaPlataforma($usuario) && $usuario->isNot($cuenta);
    }

    public function habilitar(User $usuario, User $cuenta): bool
    {
        return $this->administraLaPlataforma($usuario);
    }

    private function administraLaPlataforma(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }
}
