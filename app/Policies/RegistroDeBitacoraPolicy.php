<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\User;

/**
 * Bitácora de auditoría (RF62). La consultan coordinación y el ADMIN (D9 de
 * docs/trazabilidad.md); nadie la escribe a mano.
 */
final class RegistroDeBitacoraPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->hasAnyRole([Rol::Coordinador->value, Rol::Admin->value]);
    }
}
