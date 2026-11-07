<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Solicitud;
use DomainException;

/**
 * Una solicitud pendiente o revisada cuya fecha ya pasó no se revisa ni se
 * aprueba: aprobarla pondría en el calendario una práctica que no ocurrió.
 * Solo queda rechazarla para cerrarla (decisión del 2026-11-07).
 */
final class SolicitudVencida extends DomainException
{
    public static function noSePuedeTramitar(Solicitud $solicitud): self
    {
        return new self(sprintf(
            'La fecha de esta solicitud (%s) ya pasó: no se puede revisar ni aprobar. Si no se dictó, recházala para cerrarla.',
            $solicitud->fecha->format('d/m/Y'),
        ));
    }
}
