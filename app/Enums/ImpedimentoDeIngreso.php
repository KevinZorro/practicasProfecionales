<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Por qué alguien no puede entrar al laboratorio (RF70).
 */
enum ImpedimentoDeIngreso: string
{
    case SinFormato = 'sin_formato';
    case Bloqueado = 'bloqueado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::SinFormato => 'No tiene el formato de confidencialidad al día',
            self::Bloqueado => 'Bloqueado por coordinación',
        };
    }
}
