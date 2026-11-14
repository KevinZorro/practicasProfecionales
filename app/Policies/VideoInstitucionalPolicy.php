<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\User;
use App\Models\VideoInstitucional;

/**
 * Galería de videos institucionales de la portada (RF17). Contenido
 * público: lo gestiona solo el ADMIN, sin herencia.
 *
 * A diferencia de la estructura académica, un video sí se borra: nada lo
 * referencia y no forma parte de ningún histórico. Quitarlo de la landing
 * sin borrarlo es desactivarlo.
 *
 * Lo que aquí no está definido lo deniega el Gate, porque las pantallas de
 * Filament heredan de RecursoDelAdmin.
 */
final class VideoInstitucionalPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function view(User $usuario, VideoInstitucional $video): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function create(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function update(User $usuario, VideoInstitucional $video): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function delete(User $usuario, VideoInstitucional $video): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    /** Cambiar el orden en que salen en la landing. */
    public function reorder(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }
}
