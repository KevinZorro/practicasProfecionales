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

    public static function laListaNoEmpiezaDondeTerminaLaAnterior(string $origen): self
    {
        return new self(sprintf(
            'La lista tiene que arrancar el %s, que es el día siguiente al cierre de la anterior: si no, algún movimiento quedaría en dos listas o en ninguna.',
            $origen,
        ));
    }

    public static function elRangoEstaAlReves(): self
    {
        return new self('La fecha de fin no puede ser anterior a la de inicio.');
    }

    public static function noSeCierraHastaUnDiaFuturo(): self
    {
        // Lo que pase entre hoy y ese día no cabría en ninguna lista: la
        // siguiente arrancaría después.
        return new self('No se cierra una lista hasta un día que todavía no ha pasado.');
    }

    public static function laNecesidadYaEstaAtendida(): self
    {
        return new self('Esta necesidad ya se dio por atendida.');
    }

    public static function elMotivoEsObligatorio(): self
    {
        return new self('Hay que decir por qué se da por atendida.');
    }

    public static function laCantidadEsAlMenosUna(): self
    {
        return new self('Hay que pedir al menos una unidad.');
    }
}
