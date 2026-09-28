<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\TipoEvento;
use App\Models\User;

/**
 * Catálogo de tipos de evento (RF05, RF14). Lo gestiona solo el ADMIN, sin
 * herencia.
 *
 * No se borran, se desactivan: un tipo con eventos no se puede borrar (la
 * llave es restrict), y desactivarlo basta para que no se ofrezca en los
 * eventos nuevos.
 *
 * Lo que aquí no está definido lo deniega el Gate, porque las pantallas de
 * Filament heredan de RecursoDelAdmin.
 */
final class TipoEventoPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function view(User $usuario, TipoEvento $tipo): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function create(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function update(User $usuario, TipoEvento $tipo): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function delete(User $usuario, TipoEvento $tipo): bool
    {
        return false;
    }
}
