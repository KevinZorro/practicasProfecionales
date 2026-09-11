<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Materia;
use App\Models\Sala;
use App\Models\User;

/**
 * Filtros de los reportes (RF54-RF56).
 *
 * Viaja entero desde la pantalla hasta las exportaciones, de forma que el
 * PDF y el Excel se generan con exactamente el mismo criterio que se está
 * viendo. Si esto fuera un array suelto, sería fácil que una salida perdiera
 * un filtro por el camino y mostrara cifras distintas.
 */
final readonly class FiltroReporte
{
    public function __construct(
        public ?string $desde = null,
        public ?string $hasta = null,
        public ?int $docenteId = null,
        public ?int $materiaId = null,
        public ?int $salaId = null,
    ) {}

    public static function deObjetos(
        ?string $desde = null,
        ?string $hasta = null,
        ?User $docente = null,
        ?Materia $materia = null,
        ?Sala $sala = null,
    ): self {
        return new self($desde, $hasta, $docente?->id, $materia?->id, $sala?->id);
    }

    /**
     * Descripción legible para la cabecera del PDF y del Excel. Que el
     * lector de un archivo descargado sepa qué está mirando.
     */
    public function descripcion(): string
    {
        if ($this->desde === null && $this->hasta === null) {
            return 'Todo el histórico';
        }

        return sprintf('Del %s al %s', $this->desde ?? 'inicio', $this->hasta ?? 'hoy');
    }
}
