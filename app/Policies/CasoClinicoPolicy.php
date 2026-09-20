<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\CasoClinico;
use App\Models\User;

/**
 * Escenarios clínicos.
 *
 * El §6.1 del documento de arquitectura reserva al ADMIN la gestión de
 * materias, casos clínicos y tipos de evaluación, y el RF74 pone la
 * capacidad máxima de estudiantes en ese mismo grupo: es un dato que el
 * ADMIN define y edita.
 *
 * El nombre importa: Laravel resuelve las Policies por modelo, así que la de
 * CasoClinico tiene que llamarse CasoClinicoPolicy. Con cualquier otro
 * nombre no se descubre y el Gate deniega en silencio.
 */
final class CasoClinicoPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function view(User $usuario, CasoClinico $caso): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    /** RF74: la capacidad máxima solo la edita el ADMIN. */
    public function update(User $usuario, CasoClinico $caso): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }
}
