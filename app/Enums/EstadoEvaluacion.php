<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoEvaluacion: string
{
    case Borrador = 'borrador';
    case Finalizada = 'finalizada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Finalizada => 'Finalizada',
        };
    }
}
