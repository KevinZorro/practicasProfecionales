<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/** El enlace para recuperar la contraseña no sirve (ADR 0011). */
final class RecuperacionInvalida extends DomainException
{
    public static function enlaceVencido(): self
    {
        return new self('El enlace para recuperar la contraseña venció o ya se usó. Pide otro.');
    }
}
