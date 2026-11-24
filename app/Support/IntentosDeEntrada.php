<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Los dos topes de intentos fallidos de la entrada con contraseña
 * (ADR 0011, config/laboratorio.php).
 *
 * - Por correo e IP, corto: frena a quien prueba contraseñas desde un
 *   equipo sin cerrarle la puerta a toda la universidad, que sale por la
 *   misma IP.
 * - Solo por correo, holgado y más largo: frena a quien prueba contra una
 *   cuenta cambiando de IP.
 *
 * Solo cuentan los fallos; entrar bien los limpia.
 */
final class IntentosDeEntrada
{
    private const SEGUNDOS_DEL_TOPE_POR_EQUIPO = 60;

    private readonly string $correo;

    private readonly string $porEquipo;

    private readonly string $porCuenta;

    public function __construct(string $email, private readonly string $ip)
    {
        $this->correo = mb_strtolower(trim($email));
        $this->porEquipo = 'acceso:'.$this->correo.'|'.$ip;
        $this->porCuenta = 'acceso-cuenta:'.$this->correo;
    }

    /** Los segundos que faltan para volver a intentar, o null si puede. */
    public function segundosDeEspera(): ?int
    {
        if (RateLimiter::tooManyAttempts($this->porEquipo, $this->maximoPorEquipo())) {
            return RateLimiter::availableIn($this->porEquipo);
        }

        if (RateLimiter::tooManyAttempts($this->porCuenta, $this->maximoPorCuenta())) {
            return RateLimiter::availableIn($this->porCuenta);
        }

        return null;
    }

    public function fallo(): void
    {
        RateLimiter::hit($this->porEquipo, self::SEGUNDOS_DEL_TOPE_POR_EQUIPO);
        RateLimiter::hit($this->porCuenta, $this->minutosPorCuenta() * 60);

        // Se registra una vez, al cerrarse la puerta: quien revise el
        // registro ve el ataque sin una línea por cada intento.
        if (RateLimiter::attempts($this->porCuenta) === $this->maximoPorCuenta()) {
            Log::warning('Entrada con contraseña frenada: demasiados intentos contra una cuenta.', ['correo' => $this->correo, 'ip' => $this->ip]);
        }
    }

    /** Entró: los dos topes vuelven a cero. */
    public function exito(): void
    {
        RateLimiter::clear($this->porEquipo);
        RateLimiter::clear($this->porCuenta);
    }

    private function maximoPorEquipo(): int
    {
        return (int) config('laboratorio.acceso.intentos_por_minuto');
    }

    private function maximoPorCuenta(): int
    {
        return (int) config('laboratorio.acceso.intentos_por_cuenta');
    }

    private function minutosPorCuenta(): int
    {
        return (int) config('laboratorio.acceso.ventana_por_cuenta_minutos');
    }
}
