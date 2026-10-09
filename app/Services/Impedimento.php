<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ImpedimentoDeIngreso;

/** Un motivo por el que alguien no puede entrar, con su detalle si lo hay. */
final readonly class Impedimento
{
    public function __construct(
        public ImpedimentoDeIngreso $tipo,
        public ?string $detalle = null,
    ) {}

    public function descripcion(): string
    {
        return $this->detalle === null
            ? $this->tipo->etiqueta()
            : $this->tipo->etiqueta().': '.$this->detalle;
    }
}
