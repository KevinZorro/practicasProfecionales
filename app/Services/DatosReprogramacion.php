<?php

declare(strict_types=1);

namespace App\Services;

/**
 * La sesión reprogramada (RF61): fecha, franja y escenario nuevos, el
 * motivo y la constancia de que se habló con el docente (D4).
 */
final readonly class DatosReprogramacion
{
    /**
     * @param  string  $fecha  en formato Y-m-d
     * @param  string  $horaInicio  en formato H:i
     * @param  string  $horaFin  en formato H:i
     */
    public function __construct(
        public string $fecha,
        public string $horaInicio,
        public string $horaFin,
        public int $casoClinicoId,
        public string $motivo,
        public string $constanciaComunicacion,
    ) {}
}
