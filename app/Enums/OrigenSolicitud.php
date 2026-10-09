<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * De dónde sale una sesión: la pide el docente durante el semestre (RF27),
 * o la registra un administrativo antes del semestre desde el formato que
 * entrega coordinación (RF57).
 */
enum OrigenSolicitud: string
{
    case Docente = 'docente';
    case RegistroPrevio = 'registro_previo';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Docente => 'Solicitada por el docente',
            self::RegistroPrevio => 'Apartada antes del semestre',
        };
    }
}
