<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\RecuperacionInvalida;
use App\Exceptions\UsuarioInvalido;
use App\Mail\RecuperarContrasenaMail;
use App\Models\User;
use Illuminate\Auth\Passwords\TokenRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

/**
 * Recuperar la contraseña por correo (ADR 0011).
 *
 * Usa el repositorio de tokens del broker de Laravel (password_reset_tokens:
 * token cifrado, 60 minutos, un solo uso) y un correo propio en español por
 * la cola, como todos los demás. Sirve también para elegir la primera
 * contraseña: las cuentas de la sincronización nacen sin ella, y quien
 * recibe el correo en su buzón institucional prueba que es suyo.
 */
final class RecuperacionDeContrasenaService
{
    public function __construct(
        private readonly AccesoService $acceso,
        private readonly UsuarioService $usuarios,
    ) {}

    /**
     * Manda el enlace si hay una cuenta que pueda entrar. Si no la hay, no
     * hace nada y no lo dice: la pantalla responde igual, para no delatar
     * qué correos tienen cuenta. Tampoco manda otro si acaba de mandar uno.
     */
    public function enviarEnlace(string $email): void
    {
        $cuenta = $this->cuentaConElCorreo($email);

        if ($cuenta === null || ! $this->acceso->puedeEntrar($cuenta) || $this->tokens()->recentlyCreatedToken($cuenta)) {
            return;
        }

        $enlace = route('acceso.restablecer', ['token' => $this->tokens()->create($cuenta), 'email' => $cuenta->email]);

        Mail::to($cuenta->email)->queue(new RecuperarContrasenaMail($cuenta, $enlace, $this->minutosDeVigencia()));
    }

    /**
     * @throws RecuperacionInvalida si el enlace venció, ya se usó o no es de ese correo
     * @throws UsuarioInvalido si la contraseña es corta
     */
    public function restablecer(string $email, string $token, string $contrasena): User
    {
        $cuenta = $this->cuentaConElCorreo($email);

        if ($cuenta === null || ! $this->tokens()->exists($cuenta, $token)) {
            throw RecuperacionInvalida::enlaceVencido();
        }

        DB::transaction(function () use ($cuenta, $contrasena): void {
            $this->usuarios->fijarContrasena($cuenta, $contrasena);
            $this->tokens()->delete($cuenta);
        });

        return $cuenta;
    }

    public function minutosDeVigencia(): int
    {
        return (int) config('auth.passwords.users.expire');
    }

    private function tokens(): TokenRepositoryInterface
    {
        return Password::broker()->getRepository();
    }

    private function cuentaConElCorreo(string $email): ?User
    {
        return User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])->first();
    }
}
