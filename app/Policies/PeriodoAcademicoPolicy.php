<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\PeriodoAcademico;
use App\Models\User;

/**
 * Periodo académico (RF75).
 *
 * | Acción                     | ADMIN | Coordinador | Administrativo | Docente | Estudiante |
 * | Ver, abrir, cerrar, reabrir |   ✓   |      ✓      |       ✓        |         |            |
 *
 * El enunciado lo da a los tres por igual: quien reciba primero la noticia
 * de que empezó el semestre lo abre.
 */
final class PeriodoAcademicoPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $this->gestionaElPeriodo($usuario);
    }

    public function create(User $usuario): bool
    {
        return $this->gestionaElPeriodo($usuario);
    }

    public function cerrar(User $usuario, PeriodoAcademico $periodo): bool
    {
        return $this->gestionaElPeriodo($usuario);
    }

    public function reabrir(User $usuario, PeriodoAcademico $periodo): bool
    {
        return $this->gestionaElPeriodo($usuario);
    }

    private function gestionaElPeriodo(User $usuario): bool
    {
        return $usuario->hasAnyRole([
            Rol::Admin->value,
            Rol::Coordinador->value,
            Rol::Administrativo->value,
        ]);
    }
}
