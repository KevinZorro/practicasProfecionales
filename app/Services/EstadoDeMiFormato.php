<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoFormatoConfidencialidad;
use App\Models\FormatoConfidencialidad;

/**
 * El formato de confidencialidad de quien firma, en el periodo vigente
 * (RF51-RF53, RF75): lo que la pantalla «Hoy» le recuerda.
 *
 * La entrega en físico no es un estado del documento (CLAUDE.md, regla 7):
 * convive con él y se pregunta aparte.
 */
final readonly class EstadoDeMiFormato
{
    public function __construct(
        public ?string $periodo,
        public ?FormatoConfidencialidad $entrega,
    ) {}

    public function estado(): EstadoFormatoConfidencialidad
    {
        return $this->entrega->estado ?? EstadoFormatoConfidencialidad::Pendiente;
    }

    public function alDia(): bool
    {
        return $this->estado() === EstadoFormatoConfidencialidad::Verificado;
    }

    public function entregadoEnFisico(): bool
    {
        return $this->entrega?->entregadoEnFisico() ?? false;
    }

    /** Lo devolvieron para que lo vuelva a subir. */
    public function devuelto(): bool
    {
        return $this->estado() === EstadoFormatoConfidencialidad::Pendiente && $this->entrega?->motivo_rechazo !== null;
    }
}
