<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Sala;
use DomainException;
use Illuminate\Support\Collection;

/**
 * El grupo no cabe en las salas elegidas: su capacidad sumada, cuánta gente
 * cabe en el espacio físico, no alcanza para los estudiantes de la sesión
 * (P11). Abriendo las cortinas se pueden unir hasta tres (P16).
 */
final class SalaInsuficiente extends DomainException
{
    /**
     * @param  Collection<int, Sala>  $salas
     */
    public static function para(Collection $salas, int $estudiantes): self
    {
        return new self(sprintf(
            '%s %s caben %d personas y el grupo es de %d estudiantes. Elige una sala más grande o únele otra abriendo la cortina.',
            $salas->count() === 1 ? 'En' : 'Entre',
            $salas->pluck('nombre')->implode(' y '),
            (int) $salas->sum('capacidad'),
            $estudiantes,
        ));
    }
}
