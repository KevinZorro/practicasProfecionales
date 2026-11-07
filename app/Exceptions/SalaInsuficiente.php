<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Sala;
use DomainException;

/**
 * El grupo no cabe en la sala: su capacidad, cuánta gente cabe en el espacio
 * físico, no alcanza para los estudiantes de la sesión (decisión del
 * 2026-11-07).
 */
final class SalaInsuficiente extends DomainException
{
    public static function para(Sala $sala, int $estudiantes): self
    {
        return new self(sprintf(
            'En %s caben %d personas y el grupo es de %d estudiantes. Elige una sala más grande.',
            $sala->nombre,
            $sala->capacidad,
            $estudiantes,
        ));
    }
}
