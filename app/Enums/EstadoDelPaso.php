<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Cómo se dibuja un paso de la línea de estados de una solicitud: lo que ya
 * pasó, dónde está ahora, lo que falta, o dónde se cortó (un rechazo).
 */
enum EstadoDelPaso: string
{
    case Hecho = 'hecho';
    case Actual = 'actual';
    case Pendiente = 'pendiente';
    case Cortado = 'cortado';
}
