<?php

declare(strict_types=1);

namespace App\Enums;

// El documento de arquitectura declara eventos.tipo sin enumerar sus valores:
// estos cuatro están pendientes de confirmar con el cliente.
enum TipoEvento: string
{
    case Congreso = 'congreso';
    case Seminario = 'seminario';
    case Jornada = 'jornada';
    case Otro = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Congreso => 'Congreso',
            self::Seminario => 'Seminario',
            self::Jornada => 'Jornada',
            self::Otro => 'Otro',
        };
    }
}
