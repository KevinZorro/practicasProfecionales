<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

final class SolicitudInvalida extends DomainException
{
    public static function sinEstudiantes(): self
    {
        return new self('Indique qué estudiantes van a la sesión.');
    }

    /**
     * @param  list<string>  $nombres
     */
    public static function noSonEstudiantesActivos(array $nombres): self
    {
        return new self(sprintf(
            'Solo se pueden incluir estudiantes con cuenta activa. No lo son: %s.',
            implode(', ', $nombres),
        ));
    }

    public static function grupoInvalido(string $grupo): self
    {
        return new self(sprintf('El grupo se identifica con una o dos letras (A, B, C…); se recibió "%s".', $grupo));
    }
}
