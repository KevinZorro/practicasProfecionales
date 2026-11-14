<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoSala;
use App\Enums\EstadoSolicitud;
use App\Models\Sala;
use App\Models\Solicitud;

/**
 * Lo que el administrativo verifica al revisar una solicitud (RF30), además
 * de los faltantes de inventario, que avisa InventarioService: cruces de
 * horario, estado del espacio físico y escenario deshabilitado.
 *
 * Solo avisa. Ninguno de estos avisos impide revisar ni aprobar: la sala se
 * elige al preparar (regla 4), una novedad puede resolverse antes de la
 * clase, y quien decide si la sesión sigue es quien revisa.
 */
final class RevisionDeSolicitudService
{
    public function __construct(
        private readonly RegistroPrevioService $registroPrevio,
    ) {}

    /**
     * Vacío si la solicitud ya no se está decidiendo: aprobada o rechazada,
     * lo que dicen estos avisos ya no sirve para decidir nada.
     *
     * @return list<string>
     */
    public function advertencias(Solicitud $solicitud): array
    {
        if (! in_array($solicitud->estado, [EstadoSolicitud::Pendiente, EstadoSolicitud::Revisada], true)) {
            return [];
        }

        // Los cruces y las salas que quedan son los mismos avisos de la sesión
        // apartada (RF58): un solo criterio para la misma franja.
        $avisos = $this->registroPrevio->advertencias(
            $solicitud->fecha->format('Y-m-d'),
            $solicitud->hora_inicio,
            $solicitud->hora_fin,
            excluirId: $solicitud->id,
        );

        if ($solicitud->casoClinico !== null && ! $solicitud->casoClinico->activo) {
            $avisos[] = sprintf('El escenario «%s» está deshabilitado.', $solicitud->casoClinico->nombre);
        }

        foreach (Sala::query()->noOperativas()->orderBy('nombre')->get(['id', 'nombre', 'estado', 'novedad']) as $sala) {
            $avisos[] = $sala->estado === EstadoSala::FueraDeServicio
                ? sprintf('%s está fuera de servicio y no se asignará: %s', $sala->nombre, $sala->novedad)
                : sprintf('%s tiene una novedad: %s', $sala->nombre, $sala->novedad);
        }

        return $avisos;
    }
}
