<?php

declare(strict_types=1);

namespace App\Enums;

enum NivelFidelidad: string
{
    case Baja = 'baja';
    case Media = 'media';
    case Alta = 'alta';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Baja => 'Baja fidelidad',
            self::Media => 'Media fidelidad',
            self::Alta => 'Alta fidelidad',
        };
    }
}
