<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/** La configuración del usuario de la aplicación en PostgreSQL no sirve (S.1). */
final class UsuarioDeLaBaseInvalido extends DomainException
{
    public static function nombreInvalido(string $usuario): self
    {
        return new self(sprintf(
            '«%s» no es un nombre de usuario válido: solo minúsculas, números y guion bajo, empezando por una letra. Revisa DB_USERNAME en el .env.',
            $usuario,
        ));
    }

    public static function sinContrasena(): self
    {
        return new self('El usuario de la aplicación no tiene contraseña. Revisa DB_PASSWORD en el .env.');
    }

    public static function esElDueno(string $usuario): self
    {
        return new self(sprintf(
            '«%s» es el dueño de la base: la aplicación tiene que entrar con otro usuario. DB_USERNAME y DB_DUENO_USUARIO no pueden ser el mismo.',
            $usuario,
        ));
    }
}
