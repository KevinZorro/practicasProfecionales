<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

final class ReposicionInvalida extends DomainException
{
    public static function laListaYaEstaCerrada(): self
    {
        // Cerrarla dos veces duplicaría las líneas de la carta.
        return new self('Esta lista ya está cerrada: sus cifras son las que se presentaron y no cambian.');
    }

    public static function noHayNadaQuePedir(): self
    {
        return new self('No hay nada que pedir en ese rango de fechas: no se cierra una lista vacía.');
    }

    public static function noSeSabeQueSePide(): self
    {
        return new self('Elige un ítem del inventario o escribe qué es lo que hizo falta.');
    }

    public static function laCantidadEsAlMenosUna(): self
    {
        return new self('Hay que pedir al menos una unidad.');
    }
}
