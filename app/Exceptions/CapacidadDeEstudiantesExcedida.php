<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\CasoClinico;
use DomainException;

/**
 * Se pidieron más estudiantes de los que admite el escenario (RF74).
 *
 * El nombre es largo a propósito: "capacidad" a secas ya significa otra cosa
 * aquí (las capacidades clínicas del simulador).
 */
final class CapacidadDeEstudiantesExcedida extends DomainException
{
    public static function para(CasoClinico $caso, int $solicitados): self
    {
        return new self(sprintf(
            'El escenario "%s" admite %d estudiantes como máximo y se solicitaron %d.',
            $caso->nombre,
            (int) $caso->capacidad_maxima_estudiantes,
            $solicitados,
        ));
    }
}
