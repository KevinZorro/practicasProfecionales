<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoConsentimiento: string
{
    case Pendiente = 'pendiente';
    case Cargado = 'cargado';
    case Verificado = 'verificado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Cargado => 'Cargado',
            self::Verificado => 'Verificado',
        };
    }
}
