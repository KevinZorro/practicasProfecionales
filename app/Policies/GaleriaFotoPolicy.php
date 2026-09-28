<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\GaleriaFoto;
use App\Models\User;

/**
 * Galería de fotos de la landing (RF10). Contenido público: lo gestiona
 * solo el ADMIN, sin herencia.
 *
 * A diferencia de la estructura académica, una foto sí se borra: nada la
 * referencia y no forma parte de ningún histórico. Quitarla de la landing
 * sin borrarla es desactivarla.
 *
 * Lo que aquí no está definido lo deniega el Gate, porque las pantallas de
 * Filament heredan de RecursoDelAdmin.
 */
final class GaleriaFotoPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function view(User $usuario, GaleriaFoto $foto): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function create(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function update(User $usuario, GaleriaFoto $foto): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function delete(User $usuario, GaleriaFoto $foto): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    /** Cambiar el orden en que salen en la landing. */
    public function reorder(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }
}
