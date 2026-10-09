<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Solicitud;
use App\Models\TipoEvaluacion;
use App\Models\User;
use App\Services\Impedimento;
use DomainException;

/**
 * Las reglas del §6 del documento de arquitectura que el esquema por sí
 * solo no puede garantizar.
 */
final class EvaluacionInvalida extends DomainException
{
    public static function solicitudNoEsDeEvaluacion(Solicitud $solicitud): self
    {
        return new self(sprintf(
            'La solicitud #%d es de tipo "%s": no se puede evaluar sobre ella.',
            $solicitud->id,
            $solicitud->tipo->value,
        ));
    }

    public static function solicitudNoAprobada(Solicitud $solicitud): self
    {
        return new self(sprintf(
            'La solicitud #%d está en estado "%s": ninguna evaluación existe sin escenario aprobado.',
            $solicitud->id,
            $solicitud->estado->value,
        ));
    }

    public static function solicitudYaEvaluada(Solicitud $solicitud): self
    {
        return new self(sprintf('La solicitud #%d ya tiene una evaluación registrada.', $solicitud->id));
    }

    public static function tipoInactivo(TipoEvaluacion $tipo): self
    {
        return new self(sprintf(
            'El tipo de evaluación "%s" está desactivado: no se pueden crear evaluaciones nuevas con él.',
            $tipo->nombre,
        ));
    }

    public static function tipoAjenoALaMateria(TipoEvaluacion $tipo, Solicitud $solicitud): self
    {
        return new self(sprintf(
            'El tipo de evaluación "%s" no está asociado a la materia %s.',
            $tipo->nombre,
            $solicitud->materia->nombre,
        ));
    }

    /**
     * @param  list<Impedimento>  $impedimentos
     */
    public static function noPuedeIngresar(User $estudiante, array $impedimentos): self
    {
        return new self(sprintf(
            'No se puede evaluar a %s: %s.',
            $estudiante->nombre,
            implode('; ', array_map(static fn (Impedimento $i): string => mb_strtolower($i->descripcion()), $impedimentos)),
        ));
    }

    public static function noVaALaSesion(User $estudiante): self
    {
        return new self(sprintf('%s no está en la lista de estudiantes de esta sesión.', $estudiante->nombre));
    }

    public static function noEsBorrador(): self
    {
        return new self('Una evaluación finalizada no se modifica.');
    }

    public static function faltanResultados(int $cuantos): self
    {
        return new self(sprintf(
            'No se puede finalizar: %d estudiante(s) sin resultado registrado.',
            $cuantos,
        ));
    }
}
