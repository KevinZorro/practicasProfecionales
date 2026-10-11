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
     * Cómo se pinta la sesión en el calendario, sin otro color que los de la
     * marca: la práctica rellena de tinta y la evaluación en contorno, como
     * su etiqueta en el panel (x-tipo-de-sesion). En la vista de mes, donde
     * el evento es un punto, la clase evento-{tipo} deja hueco el de la
     * evaluación.
     *
     * @return array{fondo: string, borde: string, texto: string}
     */
    public function coloresEnElCalendario(): array
    {
        return match ($this) {
            self::Practica => ['fondo' => '#3f3f43', 'borde' => '#3f3f43', 'texto' => '#ffffff'],
            self::Evaluacion => ['fondo' => '#ffffff', 'borde' => '#3f3f43', 'texto' => '#1d1d1f'],
        };
    }
}
