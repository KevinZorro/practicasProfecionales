<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

final class NovedadInvalida extends DomainException
{
    public static function sesionNoReprogramable(): self
    {
        return new self('Solo se reprograma o se sustituye en una sesión aprobada que todavía no ha pasado.');
    }

    public static function faltaMotivo(): self
    {
        return new self('Escriba el motivo.');
    }

    public static function faltaConstancia(): self
    {
        return new self('Anote cómo y cuándo se le comunicó el cambio al docente.');
    }

    public static function sinCambios(): self
    {
        return new self('La fecha, la franja y el escenario son los mismos: no hay nada que reprogramar.');
    }

    public static function noEsDocente(): self
    {
        return new self('El reemplazo tiene que ser un docente con cuenta activa.');
    }

    public static function mismoDocente(): self
    {
        return new self('Ese docente ya es quien dicta la sesión.');
    }
}
