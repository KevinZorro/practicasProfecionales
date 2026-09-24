<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Materia;
use App\Models\User;

/**
 * Materias (RF23). Las gestiona solo el ADMIN, sin herencia: el §6.1 del
 * documento de arquitectura le reserva la estructura académica.
 *
 * No se borran, se desactivan. Una materia con solicitudes no se puede
 * borrar (la llave es restrict), y una sin ellas se llevaría en cascada sus
 * asociaciones con casos clínicos y tipos de evaluación sin avisar. Desactivarla
 * la saca del formulario del docente y conserva todo lo demás.
 *
 * Lo que aquí no está definido —deleteAny, restore, replicate...— lo deniega
 * el Gate, porque las pantallas de Filament heredan de RecursoDelAdmin.
 */
final class MateriaPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function view(User $usuario, Materia $materia): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function create(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function update(User $usuario, Materia $materia): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function delete(User $usuario, Materia $materia): bool
    {
        return false;
    }
}
