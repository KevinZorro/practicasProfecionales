<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Algo que hizo falta y hay que pedir (RF67).
 *
 * O apunta a un ítem del catálogo, o trae su propia descripción. Las dos a
 * la vez valen: "hacen falta más gasas, de las gruesas".
 */
final readonly class DatosNecesidad
{
    public function __construct(
        public int $cantidad,
        public string $justificacion,
        public ?int $itemInventarioId = null,
        public ?string $descripcion = null,
        public ?string $fecha = null,
    ) {}
}
