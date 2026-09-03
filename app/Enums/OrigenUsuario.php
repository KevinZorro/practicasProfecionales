<?php

declare(strict_types=1);

namespace App\Enums;

enum OrigenUsuario: string
{
    case Matriculado = 'matriculado';
    case Contratado = 'contratado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Matriculado => 'Matriculado',
            self::Contratado => 'Contratado',
        };
    }
}
