<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\RecuperacionInvalida;
use App\Exceptions\UsuarioInvalido;
use App\Mail\RecuperarContrasenaMail;
use App\Models\User;
use Illuminate\Auth\Passwords\TokenRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

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
            // Lo que tardaría cifrar el token: que el tiempo de respuesta
            // tampoco delate qué correos tienen cuenta.
            Hash::make(Str::random(40));

            return;
        }

        $enlace = route('acceso.restablecer', ['token' => $this->tokens()->create($cuenta), 'email' => $cuenta->email]);

        Mail::to($cuenta->email)->queue(new RecuperarContrasenaMail($cuenta, $enlace, $this->minutosDeVigencia()));
    }

    /**
     * @throws RecuperacionInvalida si el enlace venció, ya se usó o no es de ese correo
     * @throws UsuarioInvalido si la contraseña es corta o larga
     */
    public function restablecer(string $email, string $token, string $contrasena): User
    {
        // Con la cuenta bloqueada: dos envíos del mismo enlace a la vez no
        // lo usan dos veces, porque el segundo lo encuentra ya borrado.
        return DB::transaction(function () use ($email, $token, $contrasena): User {
            $cuenta = $this->cuentaBloqueadaConElCorreo($email);

            if ($cuenta === null || ! $this->tokens()->exists($cuenta, $token)) {
                throw RecuperacionInvalida::enlaceVencido();
            }

            $this->usuarios->fijarContrasena($cuenta, $contrasena);
            $this->tokens()->delete($cuenta);

            return $cuenta;
        });
    }

    /**
     * El enlace todavía sirve: es de esa cuenta, no venció y no se usó. Lo
     * pregunta la pantalla antes de pedir la contraseña nueva, para no
     * hacerla escribir dos veces y decir después que el enlace no servía.
     */
    public function enlaceVigente(string $email, string $token): bool
    {
        $cuenta = $this->cuentaConElCorreo($email);

        return $cuenta !== null && $this->tokens()->exists($cuenta, $token);
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
        return $this->consultaPorCorreo($email)->first();
    }

    private function cuentaBloqueadaConElCorreo(string $email): ?User
    {
        return $this->consultaPorCorreo($email)->lockForUpdate()->first();
    }

    /** @return Builder<User> */
    private function consultaPorCorreo(string $email): Builder
    {
        return User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))]);
    }
}
