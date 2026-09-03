<?php

declare(strict_types=1);

namespace App\Enums;

enum TipoItemInventario: string
{
    case Simulador = 'simulador';
    case EquipoClinico = 'equipo_clinico';
    case EquipoBasico = 'equipo_basico';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Simulador => 'Simulador',
            self::EquipoClinico => 'Equipo clínico',
            self::EquipoBasico => 'Equipo básico',
        };
    }

    /**
     * El nivel de fidelidad solo tiene sentido en los simuladores; en los
     * equipos queda nulo.
     */
    public function admiteNivelFidelidad(): bool
    {
        return $this === self::Simulador;
    }
}
