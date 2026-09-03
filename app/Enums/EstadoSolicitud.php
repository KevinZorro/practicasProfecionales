<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoSolicitud: string
{
    case Pendiente = 'pendiente';
    case Revisada = 'revisada';
    case Aprobada = 'aprobada';
    case Rechazada = 'rechazada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Revisada => 'Revisada',
            self::Aprobada => 'Aprobada',
            self::Rechazada => 'Rechazada',
        };
    }
}
