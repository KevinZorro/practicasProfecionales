<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoSolicitud;
use App\Events\SolicitudAprobada;
use App\Events\SolicitudRechazada;
use App\Exceptions\TransicionDeSolicitudInvalida;
use App\Models\CasoClinico;
use App\Models\ItemInventario;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reglas del flujo de solicitudes de escenario (RF27-RF35).
 */
final class SolicitudService
{
    public function __construct(private readonly PreparacionService $preparaciones) {}

    public function crear(User $docente, DatosNuevaSolicitud $datos): Solicitud
    {
        return DB::transaction(function () use ($docente, $datos): Solicitud {
            $solicitud = Solicitud::create($this->atributosIniciales($docente, $datos));
            $solicitud->items()->attach($this->itemsAAdjuntar($datos));

            return $solicitud;
        });
    }

    /**
     * Inventario que el caso clínico necesita, para precargar el formulario
     * (RF29). Es un punto de partida: el docente puede ajustar cantidades y
     * agregar equipos antes de enviar.
     *
     * @return Collection<int, ItemInventario>
     */
    public function itemsSugeridos(CasoClinico $casoClinico): Collection
    {
        return $casoClinico->items()->get();
    }

    /**
     * Historial de un docente (RF35), listo para pintar: trae materia, caso
     * clínico y la sala si el administrativo ya la asignó.
     *
     * @return LengthAwarePaginator<int, Solicitud>
     */
    public function historialDelDocente(User $docente, ?EstadoSolicitud $estado = null, int $porPagina = 15): LengthAwarePaginator
    {
        return Solicitud::query()
            ->delDocente($docente)
            ->when($estado instanceof EstadoSolicitud, fn (Builder $c) => $c->enEstado($estado))
            ->with(['materia', 'casoClinico', 'preparacion.sala'])
            ->orderByDesc('fecha')
            ->orderByDesc('hora_inicio')
            ->paginate($porPagina);
    }

    /**
     * Bandeja de revisión (RF31-RF33), ordenada por la fecha de la práctica:
     * lo que ocurre antes se atiende antes.
     *
     * @return LengthAwarePaginator<int, Solicitud>
     */
    public function bandeja(?EstadoSolicitud $estado = null, int $porPagina = 15): LengthAwarePaginator
    {
        return Solicitud::query()
            ->when($estado instanceof EstadoSolicitud, fn (Builder $c) => $c->enEstado($estado))
            ->with(['docente', 'materia', 'casoClinico', 'preparacion.sala'])
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->paginate($porPagina);
    }

    /**
     * Una solicitud con todo lo que el detalle de la bandeja enseña.
     */
    public function paraDetalle(Solicitud $solicitud): Solicitud
    {
        return $solicitud->load(['docente', 'materia', 'casoClinico', 'preparacion.sala', 'items']);
    }

    public function marcarRevisada(Solicitud $solicitud, User $administrativo): Solicitud
    {
        $this->garantizarTransicion($solicitud, EstadoSolicitud::Revisada);

        $solicitud->update([
            'estado' => EstadoSolicitud::Revisada,
            'revisada_por' => $administrativo->id,
            'revisada_at' => now(),
        ]);

        return $solicitud;
    }

    public function aprobar(Solicitud $solicitud, User $coordinador): Solicitud
    {
        $this->garantizarTransicion($solicitud, EstadoSolicitud::Aprobada);

        DB::transaction(function () use ($solicitud, $coordinador): void {
            $solicitud->update($this->atributosDeResolucion(EstadoSolicitud::Aprobada, $coordinador));
            $this->preparaciones->crearDesdeSolicitud($solicitud);
        });

        // Fuera de la transacción: si algo la revierte, no debe salir correo.
        SolicitudAprobada::dispatch($solicitud);

        return $solicitud;
    }

    public function rechazar(Solicitud $solicitud, User $coordinador, ?string $motivo = null): Solicitud
    {
        $this->garantizarTransicion($solicitud, EstadoSolicitud::Rechazada);

        $solicitud->update([
            ...$this->atributosDeResolucion(EstadoSolicitud::Rechazada, $coordinador),
            'motivo_rechazo' => $motivo,
        ]);

        SolicitudRechazada::dispatch($solicitud);

        return $solicitud;
    }

    /**
     * Reservas aprobadas de un rango de fechas, con la sala si el
     * administrativo ya la asignó (RF34).
     *
     * @return Collection<int, Solicitud>
     */
    public function paraCalendario(string $desde, string $hasta): Collection
    {
        return Solicitud::query()
            ->aprobadasEntre($desde, $hasta)
            ->with(['docente', 'materia', 'casoClinico', 'preparacion.sala'])
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function atributosIniciales(User $docente, DatosNuevaSolicitud $datos): array
    {
        return [
            'docente_id' => $docente->id,
            'materia_id' => $datos->materiaId,
            'caso_clinico_id' => $datos->casoClinicoId,
            'tipo' => $datos->tipo,
            'fecha' => $datos->fecha,
            'hora_inicio' => $datos->horaInicio,
            'hora_fin' => $datos->horaFin,
            'cantidad_estudiantes' => $datos->cantidadEstudiantes,
            'estado' => EstadoSolicitud::Pendiente,
            'observaciones' => $datos->observaciones,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function atributosDeResolucion(EstadoSolicitud $estado, User $coordinador): array
    {
        return [
            'estado' => $estado,
            'resuelta_por' => $coordinador->id,
            'resuelta_at' => now(),
        ];
    }

    /**
     * @return array<int, array{cantidad: int}>
     */
    private function itemsAAdjuntar(DatosNuevaSolicitud $datos): array
    {
        $cantidades = $datos->items !== []
            ? $datos->items
            : $this->cantidadesDelCasoClinico($datos->casoClinicoId);

        return array_map(
            static fn (int $cantidad): array => ['cantidad' => $cantidad],
            $cantidades,
        );
    }

    /**
     * @return array<int, int>
     */
    private function cantidadesDelCasoClinico(int $casoClinicoId): array
    {
        return $this->itemsSugeridos(CasoClinico::findOrFail($casoClinicoId))
            ->mapWithKeys(static fn (ItemInventario $item): array => [
                $item->id => (int) $item->pivot->cantidad,
            ])
            ->all();
    }

    private function garantizarTransicion(Solicitud $solicitud, EstadoSolicitud $destino): void
    {
        $permitidos = $this->transicionesPermitidas()[$solicitud->estado->value];

        if (! in_array($destino, $permitidos, true)) {
            throw TransicionDeSolicitudInvalida::de($solicitud->estado, $destino);
        }
    }

    /**
     * El docente solicita, el administrativo revisa y el coordinador
     * resuelve. Sin atajos: una solicitud pendiente no se aprueba sin pasar
     * por revisión, y una ya resuelta no se reabre.
     *
     * @return array<string, list<EstadoSolicitud>>
     */
    private function transicionesPermitidas(): array
    {
        return [
            EstadoSolicitud::Pendiente->value => [EstadoSolicitud::Revisada],
            EstadoSolicitud::Revisada->value => [EstadoSolicitud::Aprobada, EstadoSolicitud::Rechazada],
            EstadoSolicitud::Aprobada->value => [],
            EstadoSolicitud::Rechazada->value => [],
        ];
    }
}
