<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TipoSesion;

/**
 * Datos con los que un docente pide un escenario.
 *
 * No incluye sala: la asigna el administrativo durante la preparación,
 * después de que el coordinador apruebe (§4.4 del documento de
 * arquitectura).
 */
final readonly class DatosNuevaSolicitud
{
    /**
     * @param  string  $fecha  en formato Y-m-d
     * @param  string  $horaInicio  en formato H:i
     * @param  string  $horaFin  en formato H:i
     * @param  array<int, int>  $items  id del ítem de inventario => cantidad.
     *                                  Si llega vacío se toman los del caso
     *                                  clínico; si trae algo, manda tal cual:
     *                                  el docente pudo ajustar cantidades o
     *                                  agregar equipos.
     */
    public function __construct(
        public int $materiaId,
        public int $casoClinicoId,
        public TipoSesion $tipo,
        public string $fecha,
        public string $horaInicio,
        public string $horaFin,
        public int $cantidadEstudiantes,
        public ?string $observaciones = null,
        public array $items = [],
    ) {}
}
