<?php

declare(strict_types=1);

namespace App\Enums;

enum TipoSesion: string
{
    case Practica = 'practica';
    case Evaluacion = 'evaluacion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Practica => 'Práctica',
            self::Evaluacion => 'Evaluación',
        };
    }
}
