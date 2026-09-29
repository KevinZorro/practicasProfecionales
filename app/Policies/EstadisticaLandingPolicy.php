<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\EstadisticaLanding;
use App\Models\User;

/**
 * Estadísticas de la landing (RF01, RF10). Contenido público: lo gestiona
 * solo el ADMIN, sin herencia.
 *
 * Se borran: nada las referencia. Quitarlas de la landing sin borrarlas es
 * desactivarlas.
 *
 * Lo que aquí no está definido lo deniega el Gate, porque las pantallas de
 * Filament heredan de RecursoDelAdmin.
 */
final class EstadisticaLandingPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function view(User $usuario, EstadisticaLanding $estadistica): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function create(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function update(User $usuario, EstadisticaLanding $estadistica): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function delete(User $usuario, EstadisticaLanding $estadistica): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    /** Cambiar el orden en que salen en la landing. */
    public function reorder(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }
}
