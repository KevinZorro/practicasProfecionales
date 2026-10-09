<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\User;

/**
 * Ajustes del laboratorio, como la antelación del aviso del RF60. Solo el
 * ADMIN (D5 de docs/trazabilidad.md).
 */
final class AjusteLaboratorioPolicy
{
    public function update(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }
}
