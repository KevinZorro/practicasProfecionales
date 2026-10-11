<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\EstadoDelPaso;
use Carbon\CarbonInterface;

/**
 * Un paso de la línea de estados: la fase (un sustantivo, para que se lea
 * igual hecha, en curso o por venir), cómo va, cuándo pasó y, en el paso
 * actual o en el corte, qué está pasando ahí con palabras («Por aprobar»).
 */
final readonly class PasoDeLaLinea
{
    public function __construct(
        public string $fase,
        public EstadoDelPaso $estado,
        public ?CarbonInterface $fecha = null,
        public ?string $situacion = null,
    ) {}
}
