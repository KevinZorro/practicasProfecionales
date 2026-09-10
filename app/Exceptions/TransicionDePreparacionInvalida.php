<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\EstadoPreparacion;
use DomainException;

/**
 * El montaje avanza pendiente -> en preparación -> preparado. Cualquier
 * otro salto es un error de programación o de interfaz.
 */
final class TransicionDePreparacionInvalida extends DomainException
{
    public static function de(EstadoPreparacion $origen, EstadoPreparacion $destino): self
    {
        return new self(sprintf(
            'Una preparación en estado "%s" no puede pasar a "%s".',
            $origen->value,
            $destino->value,
        ));
    }

    public static function sinSalaAsignada(): self
    {
        return new self('No se puede dar por preparado un escenario sin sala asignada.');
    }
}
