<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * Lo que tiene más de dos semestres y se puede depurar: lo anterior al
 * comienzo del más viejo de los dos periodos conservados. Sin ese comienzo
 * (con un solo periodo) no hay corte y no se depura nada.
 */
final readonly class DatosPorDepurar
{
    /**
     * @param  list<string>  $periodosConservados  del más nuevo al más viejo
     * @param  list<string>  $periodosDeLosFormatos  los de los formatos que se depuran
     */
    public function __construct(
        public ?CarbonImmutable $corte,
        public array $periodosConservados,
        public array $periodosDeLosFormatos = [],
        public int $formatos = 0,
        public int $evaluaciones = 0,
        public int $entradasDeBitacora = 0,
    ) {}

    public function hayAlgo(): bool
    {
        return $this->formatos + $this->evaluaciones + $this->entradasDeBitacora > 0;
    }
}
