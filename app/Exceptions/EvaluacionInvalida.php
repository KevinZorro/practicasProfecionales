<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Solicitud;
use App\Models\TipoEvaluacion;
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

    public static function tipoAjenoALaMateria(TipoEvaluacion $tipo, Solicitud $solicitud): self
    {
        return new self(sprintf(
            'El tipo de evaluación "%s" no está asociado a la materia %s.',
            $tipo->nombre,
            $solicitud->materia->nombre,
        ));
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
