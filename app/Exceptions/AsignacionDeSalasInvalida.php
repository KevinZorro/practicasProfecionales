<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Sala;
use DomainException;

/** Una sesión ocupa de una a tres salas, unidas abriendo las cortinas (P16). */
final class AsignacionDeSalasInvalida extends DomainException
{
    public static function cantidad(int $maximo): self
    {
        return new self(sprintf('Elige entre 1 y %d salas: es lo que se puede unir abriendo las cortinas.', $maximo));
    }

    public static function salaInexistente(): self
    {
        return new self('Alguna de las salas elegidas ya no existe. Vuelve a abrir el montaje.');
    }

    public static function fueraDeServicio(Sala $sala): self
    {
        return new self(sprintf('%s está fuera de servicio: %s', $sala->nombre, $sala->novedad));
    }
}
