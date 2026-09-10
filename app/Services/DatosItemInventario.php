<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoItemInventario;
use App\Enums\NivelFidelidad;
use App\Enums\TipoItemInventario;

/**
 * Datos de un ítem de inventario.
 *
 * nivelFidelidad viaja aparte del resto en InventarioService: solo el ADMIN
 * puede fijarlo (RF39) y solo tiene sentido en simuladores.
 */
final readonly class DatosItemInventario
{
    public function __construct(
        public string $nombre,
        public TipoItemInventario $tipo,
        public int $cantidadTotal,
        public ?string $descripcion = null,
        public EstadoItemInventario $estado = EstadoItemInventario::Disponible,
        public bool $activo = true,
        public ?NivelFidelidad $nivelFidelidad = null,
    ) {}
}
