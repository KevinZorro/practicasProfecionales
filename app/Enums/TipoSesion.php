<?php

declare(strict_types=1);

namespace App\Enums;

enum TipoSesion: string
{
    case Practica = 'practica';
    case Evaluacion = 'evaluacion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Practica => 'Práctica',
            self::Evaluacion => 'Evaluación',
        };
    }

    /**
     * El color de la sesión en el calendario: la práctica en tinta y la
     * evaluación en violeta, los mismos tonos de su etiqueta en el panel
     * (x-tipo-de-sesion). No es el único distintivo: el evento lleva además
     * la clase evento-{tipo}.
     */
    public function colorEnElCalendario(): string
    {
        return match ($this) {
            self::Practica => '#3f3f43',
            self::Evaluacion => '#6d28d9',
        };
    }
}
