<?php

declare(strict_types=1);

namespace App\Services;

/** Lo que hizo (o no hizo) una pasada de la sincronización (RF20). */
final readonly class ResultadoSincronizacion
{
    public function __construct(
        public int $creadas = 0,
        public int $actualizadas = 0,
        public int $desactivadas = 0,
        public int $reactivadas = 0,
        public bool $detenida = false,
        public ?string $motivoDeLaDetencion = null,
    ) {}

    public function resumen(): string
    {
        if ($this->detenida) {
            return 'Sincronización detenida sin cambios: '.$this->motivoDeLaDetencion;
        }

        return sprintf(
            'Sincronización hecha: %d creadas, %d actualizadas, %d desactivadas, %d reactivadas.',
            $this->creadas,
            $this->actualizadas,
            $this->desactivadas,
            $this->reactivadas,
        );
    }
}
