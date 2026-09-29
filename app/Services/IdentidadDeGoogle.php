<?php

declare(strict_types=1);

namespace App\Services;

use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User as CuentaDeSocialite;

/**
 * Lo que Google dice de quien acaba de entrar (RF18).
 *
 * AccesoService decide sobre esto y no sobre el objeto de Socialite: así la
 * regla se prueba sin simular a Google, y si un día se cambia de paquete, la
 * decisión no se entera.
 */
final readonly class IdentidadDeGoogle
{
    public function __construct(
        // "sub" de Google: no cambia aunque la persona cambie de correo.
        public string $id,
        public string $email,
        public bool $emailVerificado,
        // "hd": el dominio de Google Workspace de la cuenta. Nulo en una
        // cuenta personal de Gmail.
        public ?string $dominio,
    ) {}

    public static function desdeSocialite(CuentaDeSocialite $cuenta): self
    {
        // Los datos crudos de Google solo los trae AbstractUser. Sin ellos no
        // se sabe si el correo está verificado, y se trata como no
        // verificado: nadie entra por un dato que falta.
        /** @var array<string, mixed> $datos */
        $datos = $cuenta instanceof AbstractUser ? $cuenta->getRaw() : [];

        return new self(
            id: (string) $cuenta->getId(),
            email: (string) $cuenta->getEmail(),
            emailVerificado: ($datos['email_verified'] ?? false) === true,
            dominio: isset($datos['hd']) ? (string) $datos['hd'] : null,
        );
    }
}
