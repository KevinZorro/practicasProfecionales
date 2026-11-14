<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoSala;
use App\Exceptions\EstadoDeSalaInvalido;
use App\Models\Sala;
use App\Models\UbicacionDeSala;
use App\Models\User;

/**
 * Ubicación de las salas (RF65) y estado del espacio físico (RF30).
 *
 * El catálogo de salas es un recurso de Filament sin reglas; lo que se
 * decide aquí es el rastro de la reubicación —cada vez que cambia el
 * bloque, el piso o el número, queda una fila con quién lo registró— y el
 * estado de hoy de cada sala, que anota el administrativo.
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

    /**
     * El estado de hoy del espacio físico (RF30). Sin historial: se guarda
     * el último, con quién y cuándo. Volver a operativa borra la nota, que
     * ya no describe nada.
     */
    public function cambiarEstado(Sala $sala, EstadoSala $estado, ?string $novedad, User $actor): Sala
    {
        $novedad = $novedad === null ? null : trim($novedad);

        if ($estado->exigeNovedad() && ($novedad === null || $novedad === '')) {
            throw EstadoDeSalaInvalido::sinNovedad($estado);
        }

        $sala->forceFill([
            'estado' => $estado,
            'novedad' => $estado->exigeNovedad() ? $novedad : null,
            'estado_actualizado_at' => now(),
            'estado_actualizado_por' => $actor->id,
        ])->save();

        return $sala;
    }
}
