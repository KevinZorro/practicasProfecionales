<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Evaluacion;
use App\Models\User;

/**
 * Permisos del §6.1 del documento de arquitectura:
 *
 * | Acción                          | ADMIN | Coordinador | Administrativo | Docente | Estudiante |
 * | Crear y registrar evaluaciones  |       |             |                |    ✓    |            |
 * | Consultar resultados propios    |       |             |                |         |     ✓      |
 * | Generar reportes                |   ✓   |      ✓      |                |         |            |
 *
 * A diferencia de las solicitudes y del montaje, aquí el ADMIN sí entra:
 * la fila de reportes lo incluye.
 */
final class EvaluacionPolicy
{
    public function create(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Docente->value);
    }

    /**
     * El docente ve las suyas; coordinación y ADMIN, todas, para reportes.
     * Un docente no ve las evaluaciones de otro docente.
     */
    public function view(User $usuario, Evaluacion $evaluacion): bool
    {
        return $this->esSuya($usuario, $evaluacion) || $this->generaReportes($usuario);
    }

    public function viewAny(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Docente->value) || $this->generaReportes($usuario);
    }

    /**
     * Registrar el checklist, los resultados y las observaciones. Solo el
     * docente que la creó; que además esté en borrador lo comprueba
     * EvaluacionService, porque es regla de negocio y no autorización.
     */
    public function registrar(User $usuario, Evaluacion $evaluacion): bool
    {
        return $this->esSuya($usuario, $evaluacion);
    }

    public function finalizar(User $usuario, Evaluacion $evaluacion): bool
    {
        return $this->esSuya($usuario, $evaluacion);
    }

    private function esSuya(User $usuario, Evaluacion $evaluacion): bool
    {
        return $evaluacion->docente_id === $usuario->id;
    }

    private function generaReportes(User $usuario): bool
    {
        return $usuario->hasAnyRole([Rol::Admin->value, Rol::Coordinador->value]);
    }
}
