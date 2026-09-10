<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\EvaluacionEstudiante;
use App\Models\User;

/**
 * El resultado de un estudiante concreto dentro de una evaluación.
 *
 * Va en su propia Policy y no en EvaluacionPolicy porque Laravel las
 * resuelve por modelo: un permiso sobre EvaluacionEstudiante declarado en
 * la Policy de Evaluacion nunca se llegaría a consultar.
 */
final class EvaluacionEstudiantePolicy
{
    /**
     * Lo ve el propio estudiante (RF49), el docente que lo evaluó, y
     * coordinación o el ADMIN para los reportes del §6.1.
     */
    public function view(User $usuario, EvaluacionEstudiante $registro): bool
    {
        return $registro->estudiante_id === $usuario->id
            || $registro->evaluacion->docente_id === $usuario->id
            || $usuario->hasAnyRole([Rol::Admin->value, Rol::Coordinador->value]);
    }
}
