<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoItemInventario: string
{
    case Disponible = 'disponible';
    case Mantenimiento = 'mantenimiento';
    case Baja = 'baja';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Disponible => 'Disponible',
            self::Mantenimiento => 'En mantenimiento',
            self::Baja => 'Dado de baja',
        };
    }
}
