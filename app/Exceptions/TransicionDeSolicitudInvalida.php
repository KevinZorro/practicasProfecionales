<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\EstadoSolicitud;
use DomainException;

/**
 * El flujo de una solicitud es: el docente solicita, el administrativo
 * revisa y el coordinador aprueba o rechaza. Cualquier salto entre estados
 * es un error de programación o de interfaz, no una situación esperable,
 * así que falla en voz alta en lugar de quedarse callado.
 */
final class TransicionDeSolicitudInvalida extends DomainException
{
    public static function de(EstadoSolicitud $origen, EstadoSolicitud $destino): self
    {
        return new self(sprintf(
            'Una solicitud en estado "%s" no puede pasar a "%s".',
            $origen->value,
            $destino->value,
        ));
    }
}
