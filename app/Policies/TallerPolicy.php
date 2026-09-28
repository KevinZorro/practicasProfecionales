<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Taller;
use App\Models\User;

/**
 * Talleres de la landing (RF04, RF13). Contenido público: lo gestiona solo
 * el ADMIN, sin herencia.
 *
 * Un taller se borra mientras nadie haya dejado sus datos en su formulario
 * de interés (RF09). Con solicitudes, borrarlo se llevaría datos de
 * personas (la llave es restrict): se desactiva.
 *
 * Lo que aquí no está definido lo deniega el Gate, porque las pantallas de
 * Filament heredan de RecursoDelAdmin.
 */
final class TallerPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function view(User $usuario, Taller $taller): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function create(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function update(User $usuario, Taller $taller): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function delete(User $usuario, Taller $taller): bool
    {
        return $usuario->hasRole(Rol::Admin->value)
            && $taller->solicitudesInformacion()->doesntExist();
    }

    /** Cambiar el orden en que salen en la landing. */
    public function reorder(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }
}
