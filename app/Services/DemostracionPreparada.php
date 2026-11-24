<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Solicitud;

/** Lo que dejó demo:preparar, para armar la guía de la consola. */
final readonly class DemostracionPreparada
{
    /**
     * @param  array<string, CuentaDeDemostracion>  $cuentas  por clave: docente, docente2, admvo, coord
     * @param  array<string, Solicitud>  $solicitudes  por el camino del flujo que prueba cada una
     */
    public function __construct(
        public array $cuentas,
        public array $solicitudes,
    ) {}
}
