<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoPreparacion: string
{
    case Pendiente = 'pendiente';
    case EnPreparacion = 'en_preparacion';
    case Preparado = 'preparado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::EnPreparacion => 'En preparación',
            self::Preparado => 'Preparado',
        };
    }
}
