<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * El RF04 define la modalidad de un taller como virtual o presencial.
 */
enum ModalidadTaller: string
{
    case Presencial = 'presencial';
    case Virtual = 'virtual';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Presencial => 'Presencial',
            self::Virtual => 'Virtual',
        };
    }
}
