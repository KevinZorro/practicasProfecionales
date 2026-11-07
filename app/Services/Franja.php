<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Solicitud;

/**
 * El día y las horas de una sesión. Dos franjas se cruzan si cada una
 * empieza antes de que la otra termine (Solicitud::scopeQueSeSolapanCon).
 */
final readonly class Franja
{
    public function __construct(
        public string $fecha,
        public string $horaInicio,
        public string $horaFin,
    ) {}

    public static function deSolicitud(Solicitud $solicitud): self
    {
        return new self($solicitud->fecha->format('Y-m-d'), $solicitud->hora_inicio, $solicitud->hora_fin);
    }
}
