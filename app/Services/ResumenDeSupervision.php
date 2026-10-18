<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Lo que «Hoy» enseña a un docente supervisor (RF77): cuántos docentes tiene
 * a su cargo y cuántas evaluaciones finalizaron en el periodo vigente.
 */
final readonly class ResumenDeSupervision
{
    public function __construct(
        public int $docentes,
        public int $evaluaciones,
        public ?string $periodo,
    ) {}
}
