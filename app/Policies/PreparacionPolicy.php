<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Preparacion;
use App\Models\User;

/**
 * Permisos del §6.1 del documento de arquitectura:
 *
 * | Acción                        | ADMIN | Coordinador | Administrativo | Docente | Estudiante |
 * | Asignar sala y preparar       |       |      ✓      |       ✓        |         |            |
 *
 * El ADMIN no interviene en el montaje, igual que no interviene en el flujo
 * de solicitudes.
 */
final class PreparacionPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $this->montaEscenarios($usuario);
    }

    public function view(User $usuario, Preparacion $preparacion): bool
    {
        return $this->montaEscenarios($usuario);
    }

    public function asignarSala(User $usuario, Preparacion $preparacion): bool
    {
        return $this->montaEscenarios($usuario);
    }

    /**
     * Cubre cambiar el estado del montaje, marcar ítems alistados y dejar
     * observaciones: son la misma tarea operativa.
     */
    public function preparar(User $usuario, Preparacion $preparacion): bool
    {
        return $this->montaEscenarios($usuario);
    }

    /**
     * El coordinador hereda todo lo del administrativo. La herencia se
     * expresa una sola vez, aquí.
     */
    private function montaEscenarios(User $usuario): bool
    {
        return $usuario->hasAnyRole([Rol::Administrativo->value, Rol::Coordinador->value]);
    }
}
