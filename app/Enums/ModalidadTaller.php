<?php

declare(strict_types=1);

namespace App\Enums;

// El documento de arquitectura declara talleres.modalidad sin enumerar sus
// valores: estos tres están pendientes de confirmar con el cliente.
enum ModalidadTaller: string
{
    case Presencial = 'presencial';
    case Virtual = 'virtual';
    case Mixta = 'mixta';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Presencial => 'Presencial',
            self::Virtual => 'Virtual',
            self::Mixta => 'Mixta',
        };
    }
}
