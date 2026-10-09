<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Bloqueo;
use App\Models\User;

/**
 * Bloqueos de acceso al laboratorio (RF68).
 *
 * | Acción                     | ADMIN | Coordinador | Administrativo | Docente | Estudiante |
 * | Ver, bloquear y levantar   |   ✓   |      ✓      |                |         |            |
 *
 * El enunciado se lo da a coordinación; el ADMIN lo conserva igual que
 * aprueba en su ausencia (D2 de docs/trazabilidad.md). El administrativo no
 * bloquea, pero ve en la lista de cada sesión quién está bloqueado (RF70).
 */
final class BloqueoPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $this->bloquea($usuario);
    }

    public function create(User $usuario): bool
    {
        return $this->bloquea($usuario);
    }

    public function levantar(User $usuario, Bloqueo $bloqueo): bool
    {
        return $this->bloquea($usuario);
    }

    private function bloquea(User $usuario): bool
    {
        return $usuario->hasAnyRole([Rol::Coordinador->value, Rol::Admin->value]);
    }
}
