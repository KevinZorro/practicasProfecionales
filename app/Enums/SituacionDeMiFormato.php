<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lo que la insignia le dice a quien firma sobre su formato de
 * confidencialidad del periodo, en «Hoy» y en «Mi formato» por igual.
 *
 * No es el estado del documento (EstadoFormatoConfidencialidad): junta ese
 * estado con la entrega en papel, que convive con él (CLAUDE.md, regla 7).
 * Lo calcula App\Services\EstadoDeMiFormato; no se guarda.
 */
enum SituacionDeMiFormato: string
{
    case AlDia = 'al_dia';
    case EnRevision = 'en_revision';
    case EntregadoEnPapel = 'entregado_en_papel';
    case Devuelto = 'devuelto';
    case Pendiente = 'pendiente';

    public function etiqueta(): string
    {
        return match ($this) {
            self::AlDia => 'Al día',
            self::EnRevision => 'En revisión',
            self::EntregadoEnPapel => 'Entregado en papel',
            self::Devuelto => 'Devuelto',
            self::Pendiente => 'Pendiente',
        };
    }
}
