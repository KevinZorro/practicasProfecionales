<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

final class SupervisionInvalida extends DomainException
{
    public static function noEsSupervisor(): self
    {
        return new self('Esta persona no tiene vigente el rol de docente supervisor: asígnaselo primero.');
    }

    public static function noEsDocente(): self
    {
        return new self('Solo se pueden asignar docentes: esta persona no tiene vigente el rol de docente.');
    }

    public static function unoMismo(): self
    {
        return new self('Un supervisor no puede supervisarse a sí mismo.');
    }

    public static function yaLoSupervisa(): self
    {
        return new self('Este docente ya está a cargo de este supervisor.');
    }

    public static function noLoSupervisa(): self
    {
        return new self('Este docente no está a cargo de este supervisor.');
    }
}
