<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

final class UsuarioInvalido extends DomainException
{
    public static function correoRepetido(string $email): self
    {
        return new self(sprintf('Ya hay una cuenta con el correo %s.', $email));
    }

    public static function noSeDeshabilitaASiMismo(): self
    {
        return new self('No puede deshabilitar su propia cuenta.');
    }

    public static function yaEstaDeshabilitado(): self
    {
        return new self('Esa cuenta ya está deshabilitada.');
    }

    public static function noEstaDeshabilitado(): self
    {
        return new self('Esa cuenta no está deshabilitada.');
    }

    public static function sinMotivo(): self
    {
        return new self('Escriba el motivo.');
    }
}
