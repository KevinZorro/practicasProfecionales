<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Supervision;
use App\Models\User;

/**
 * Quién reparte docentes entre supervisores (RF77): solo el ADMIN, sin
 * herencia, igual que el reparto de roles.
 *
 * | Acción                                   | ADMIN | Coordinador | Administrativo | Docente | Supervisor | Estudiante |
 * | Asignar o retirar docentes supervisados  |   ✓   |             |                |         |            |            |
 *
 * El nombre importa: Laravel resuelve las Policies por modelo, así que la
 * de Supervision tiene que llamarse SupervisionPolicy.
 */
final class SupervisionPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $this->administraLaPlataforma($usuario);
    }

    public function gestionar(User $usuario, ?Supervision $supervision = null): bool
    {
        return $this->administraLaPlataforma($usuario);
    }

    private function administraLaPlataforma(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }
}
