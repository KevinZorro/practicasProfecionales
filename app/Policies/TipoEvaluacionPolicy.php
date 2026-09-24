<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\TipoEvaluacion;
use App\Models\User;

/**
 * Tipos de evaluación y su checklist (RF26). Los gestiona solo el ADMIN,
 * sin herencia: el §6.1 del documento de arquitectura le reserva la
 * estructura académica.
 *
 * No se borran, se desactivan. Uno con evaluaciones no se puede borrar (la
 * llave es restrict), y uno sin ellas se llevaría en cascada su checklist y
 * sus materias.
 *
 * Lo que aquí no está definido —deleteAny, restore, replicate...— lo deniega
 * el Gate, porque las pantallas de Filament heredan de RecursoDelAdmin.
 */
final class TipoEvaluacionPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function view(User $usuario, TipoEvaluacion $tipo): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function create(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function update(User $usuario, TipoEvaluacion $tipo): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function delete(User $usuario, TipoEvaluacion $tipo): bool
    {
        return false;
    }
}
