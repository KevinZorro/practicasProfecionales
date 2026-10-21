<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * De dónde sale la cifra de estudiantes de la landing: la calcula el sistema
 * de las sesiones realizadas, o la escribe el ADMIN a mano.
 */
enum ModoCifraDeEstudiantes: string
{
    case Automatico = 'automatico';
    case Manual = 'manual';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Automatico => 'Automático: la calcula el sistema',
            self::Manual => 'Manual: la escribo yo',
        };
    }
}
