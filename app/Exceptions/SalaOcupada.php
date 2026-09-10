<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Preparacion;
use App\Models\Sala;
use DomainException;

/**
 * Dos escenarios no se montan en la misma sala a la vez. El mensaje lleva
 * los datos del choque para que el administrativo sepa con qué compite sin
 * tener que buscarlo.
 */
final class SalaOcupada extends DomainException
{
    public static function por(Sala $sala, Preparacion $conflicto): self
    {
        $solicitud = $conflicto->solicitud;

        return new self(sprintf(
            'La sala %s ya está ocupada el %s de %s a %s por la solicitud #%d.',
            $sala->nombre,
            $solicitud->fecha->format('d/m/Y'),
            $solicitud->hora_inicio,
            $solicitud->hora_fin,
            $solicitud->id,
        ));
    }
}
