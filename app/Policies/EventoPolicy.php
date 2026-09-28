<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Evento;
use App\Models\User;

/**
 * Eventos de la landing (RF05, RF14). Contenido público: lo gestiona
 * solo el ADMIN, sin herencia.
 *
 * A diferencia de la estructura académica, un evento sí se borra: nada lo
 * referencia y no forma parte de ningún histórico. Quitarlo de la landing
 * sin borrarlo es desactivarlo.
 *
 * Lo que aquí no está definido lo deniega el Gate, porque las pantallas de
 * Filament heredan de RecursoDelAdmin.
 */
final class EventoPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function view(User $usuario, Evento $evento): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function create(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function update(User $usuario, Evento $evento): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function delete(User $usuario, Evento $evento): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    /** Cambiar el orden en que salen en la landing. */
    public function reorder(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }
}
