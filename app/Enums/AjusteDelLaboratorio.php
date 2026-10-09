<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Valores que el ADMIN cambia sin desplegar. Cada uno trae su valor por
 * defecto, que rige mientras nadie lo haya guardado.
 */
enum AjusteDelLaboratorio: string
{
    /** Con cuántos días de antelación se avisa de una sesión sin formato intramural (RF60). */
    case DiasDeAvisoIntramural = 'dias_de_aviso_intramural';

    public function valorPorDefecto(): string
    {
        return match ($this) {
            self::DiasDeAvisoIntramural => '3',
        };
    }
}
