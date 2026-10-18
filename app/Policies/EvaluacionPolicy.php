<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Evaluacion;
use App\Models\Solicitud;
use App\Models\User;

/**
 * Permisos del §6.1 del documento de arquitectura:
 *
 * | Acción                          | ADMIN | Coordinador | Administrativo | Docente | Estudiante |
 * | Crear y registrar evaluaciones  |       |             |                |    ✓    |            |
 * | Consultar resultados propios    |       |             |                |         |     ✓      |
 * | Consultar los de sus docentes   |       |             |                |         | supervisor |
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
     * Registrar la evaluación de una sesión: quien la dicta, que con una
     * sustitución (RF73) es el reemplazo y no el titular. Ni coordinación
     * evalúa por el docente.
     */
    public function registrarEnSesion(User $usuario, Solicitud $solicitud): bool
    {
        return $this->create($usuario) && $solicitud->idDelDocenteQueDicta() === $usuario->id;
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
     * El docente supervisor consulta los resultados de las evaluaciones de
     * los docentes que tiene a cargo (RF77). Es solo lectura y no abre
     * ninguna otra pantalla de evaluaciones: viewAny y view siguen siendo
     * del docente, de coordinación y del ADMIN.
     */
    public function supervisar(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Supervisor->value);
    }

    /** El listado de todas las evaluaciones, no solo las propias. */
    public function verTodas(User $usuario): bool
    {
        return $this->generaReportes($usuario);
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
