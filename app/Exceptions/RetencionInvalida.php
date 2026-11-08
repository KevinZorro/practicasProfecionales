<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/** La depuración de lo que tiene más de dos semestres no procede. */
final class RetencionInvalida extends DomainException
{
    public static function nadaQueDepurar(): self
    {
        return new self('No hay nada con más de dos semestres: todo lo que hay se conserva.');
    }

    public static function sinFormatosQueDescargar(): self
    {
        return new self('No hay formatos firmados de más de dos semestres que descargar.');
    }
}
