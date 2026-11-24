<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/**
 * Quien volvió de Google no puede entrar (RF18, regla 8). El mensaje es el
 * que ve la persona en la pantalla de ingreso.
 */
final class AccesoRechazado extends DomainException
{
    public static function correoSinVerificar(): self
    {
        return new self('Google no ha verificado el correo de esa cuenta. Entra con tu cuenta institucional.');
    }

    public static function fueraDelDominio(string $dominio): self
    {
        return new self("Entra con tu cuenta institucional, la que termina en @{$dominio}.");
    }

    public static function sinDominioConfigurado(): self
    {
        // La causa va al registro; a la persona no se le explica la configuración.
        return new self('La entrada con Google no está configurada del todo. Avisa al laboratorio.');
    }

    public static function sinCuenta(): self
    {
        return new self('No hay una cuenta de la plataforma para ese correo. Si eres estudiante, docente o funcionario del laboratorio, comunícate con el laboratorio.');
    }

    public static function vinculadaAOtraCuenta(): self
    {
        return new self('Ese correo ya está vinculado a otra cuenta de Google. Comunícate con el laboratorio.');
    }

    /**
     * El mismo mensaje si el correo no existe, no tiene contraseña o la
     * contraseña no coincide: no se le dice a nadie qué correos tienen cuenta.
     */
    public static function credencialesIncorrectas(): self
    {
        return new self('El correo o la contraseña no son correctos.');
    }

    public static function demasiadosIntentos(int $segundos): self
    {
        return new self("Demasiados intentos. Vuelve a intentarlo en {$segundos} segundos.");
    }

    public static function inactivo(string $motivo): self
    {
        return new self($motivo);
    }
}
