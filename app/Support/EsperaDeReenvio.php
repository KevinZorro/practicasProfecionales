<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Cuánto hay que esperar para pedir otro enlace de recuperación al mismo
 * correo (ADR 0011). La espera crece con cada envío —1, 3, 5, 10…
 * minutos (config/laboratorio.php)— y vuelve a empezar cuando pasa un día
 * sin pedirlo.
 *
 * Cuenta por correo escrito, tenga o no cuenta: la pantalla responde
 * igual a todos, así no delata qué correos existen. Y no por IP, para que
 * nadie llene el buzón de otro cambiando de equipo.
 */
final class EsperaDeReenvio
{
    private readonly string $clave;

    public function __construct(string $email)
    {
        $this->clave = 'recuperar-reenvio:'.mb_strtolower(trim($email));
    }

    /** Los segundos que faltan para poder pedir otro; 0 si ya puede. */
    public function segundosRestantes(): int
    {
        $siguiente = $this->estado()['siguiente'];

        return max(0, $siguiente - now()->getTimestamp());
    }

    /** Anota un envío y fija cuándo se permite el siguiente. */
    public function registrarEnvio(): void
    {
        $envios = $this->estado()['envios'] + 1;
        $escalones = $this->escalonesEnMinutos();
        $minutos = $escalones[min($envios, count($escalones)) - 1];
        $segundos = $minutos * 60;

        Cache::put(
            $this->clave,
            ['envios' => $envios, 'siguiente' => now()->getTimestamp() + $segundos],
            now()->addHours((int) config('laboratorio.acceso.reenvio_reinicio_horas')),
        );
    }

    /** @return array{envios: int, siguiente: int} */
    private function estado(): array
    {
        $guardado = Cache::get($this->clave);

        return is_array($guardado)
            ? ['envios' => (int) $guardado['envios'], 'siguiente' => (int) $guardado['siguiente']]
            : ['envios' => 0, 'siguiente' => 0];
    }

    /** @return non-empty-list<int> */
    private function escalonesEnMinutos(): array
    {
        /** @var non-empty-list<int> $escalones */
        $escalones = config('laboratorio.acceso.reenvio_minutos');

        return $escalones;
    }
}
