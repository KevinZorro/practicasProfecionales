<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Sala;
use App\Models\UbicacionDeSala;
use App\Models\User;

/**
 * Ubicación de las salas (RF65).
 *
 * El catálogo de salas es un recurso de Filament sin reglas; lo único que
 * se decide aquí es el rastro de la reubicación: cada vez que cambia el
 * bloque, el piso o el número, queda una fila con quién lo registró.
 */
final class SalaService
{
    /**
     * Anota la ubicación actual de la sala si es distinta de la última
     * registrada. Se llama después de crearla o editarla; si no cambió la
     * ubicación, no hace nada.
     */
    public function registrarUbicacion(Sala $sala, User $actor): ?UbicacionDeSala
    {
        if ($sala->ubicacion() === null) {
            return null;
        }

        $ultima = $sala->ubicaciones()->first();

        if ($ultima instanceof UbicacionDeSala && $ultima->descripcion() === $sala->ubicacion()) {
            return null;
        }

        return $sala->ubicaciones()->create([
            'bloque' => $sala->bloque,
            'piso' => $sala->piso,
            'numero' => $sala->numero,
            'registrada_por' => $actor->id,
        ]);
    }
}
