<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoPreparacion;
use App\Exceptions\SalaOcupada;
use App\Exceptions\TransicionDePreparacionInvalida;
use App\Models\ItemInventario;
use App\Models\Preparacion;
use App\Models\Sala;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Montaje físico de los escenarios aprobados (RF36-RF37).
 *
 * La preparación nace al aprobar la solicitud, con sala nula. Este servicio
 * cubre lo que pasa después: el administrativo asigna la sala y alista el
 * material, minutos antes de la clase.
 */
final class PreparacionService
{
    /**
     * Se llama desde SolicitudService al aprobar, dentro de su transacción.
     */
    public function crearDesdeSolicitud(Solicitud $solicitud): Preparacion
    {
        // La sala llega nula: la asigna el administrativo el día de la
        // práctica, no el coordinador al aprobar (§4.5 del documento).
        $preparacion = $solicitud->preparacion()->create([
            'sala_id' => null,
            'estado' => EstadoPreparacion::Pendiente,
        ]);

        $this->copiarItemsDesdeSolicitud($preparacion);

        return $preparacion;
    }

    /**
     * Copia a preparacion_item lo que el docente pidió, todo sin alistar.
     *
     * Se copia al aprobar y no al abrir la preparación por tres razones:
     * cae dentro de la misma transacción, así que o queda todo o no queda
     * nada; abrir el tablero es una lectura y una lectura no debe escribir;
     * y congela lo que se aprobó, sin depender de cambios posteriores en la
     * solicitud.
     */
    public function copiarItemsDesdeSolicitud(Preparacion $preparacion): void
    {
        $pedidos = $preparacion->solicitud->items;

        $preparacion->items()->sync($pedidos->mapWithKeys(
            static fn (ItemInventario $item): array => [
                $item->id => ['cantidad' => (int) $item->pivot->cantidad, 'alistado' => false],
            ],
        )->all());
    }

    public function asignarSala(Preparacion $preparacion, Sala $sala): Preparacion
    {
        $conflicto = $this->preparacionSolapada($preparacion, $sala);

        if ($conflicto instanceof Preparacion) {
            throw SalaOcupada::por($sala, $conflicto);
        }

        $preparacion->update(['sala_id' => $sala->id]);

        return $preparacion;
    }

    public function marcarItemAlistado(Preparacion $preparacion, ItemInventario $item): void
    {
        $preparacion->items()->updateExistingPivot($item->id, ['alistado' => true]);
    }

    public function desmarcarItemAlistado(Preparacion $preparacion, ItemInventario $item): void
    {
        $preparacion->items()->updateExistingPivot($item->id, ['alistado' => false]);
    }

    public function cambiarEstado(
        Preparacion $preparacion,
        EstadoPreparacion $destino,
        User $administrativo,
    ): Preparacion {
        $this->garantizarTransicion($preparacion, $destino);

        $preparacion->update([
            'estado' => $destino,
            ...$this->marcasDeMontaje($destino, $administrativo),
        ]);

        return $preparacion;
    }

    public function registrarObservaciones(Preparacion $preparacion, ?string $observaciones): Preparacion
    {
        $preparacion->update(['observaciones' => $observaciones]);

        return $preparacion;
    }

    /**
     * Tablero del día (RF36): qué hay que montar hoy, para quién y con qué.
     *
     * @return Collection<int, Preparacion>
     */
    public function tableroDelDia(string $fecha): Collection
    {
        return Preparacion::query()
            ->deLaFecha($fecha)
            ->with(['solicitud.docente', 'solicitud.materia', 'solicitud.casoClinico', 'sala', 'items'])
            ->orderBy(Solicitud::select('hora_inicio')->whereColumn('solicitudes.id', 'preparaciones.solicitud_id'))
            ->get();
    }

    /**
     * Qué tiene reservado una sala en un rango. Alimenta la validación de
     * solapamiento y sirve para consultarla desde la interfaz.
     *
     * @return Collection<int, Preparacion>
     */
    public function ocupacionDeSala(Sala $sala, string $desde, string $hasta): Collection
    {
        return Preparacion::query()
            ->where('sala_id', $sala->id)
            ->whereHas('solicitud', static fn (Builder $consulta) => $consulta->whereBetween('fecha', [$desde, $hasta]))
            ->with(['solicitud.docente', 'solicitud.materia'])
            ->get();
    }

    /**
     * Otra preparación en la misma sala, el mismo día, con las franjas
     * horarias pisándose. Dos franjas se solapan cuando cada una empieza
     * antes de que la otra termine.
     */
    private function preparacionSolapada(Preparacion $preparacion, Sala $sala): ?Preparacion
    {
        $solicitud = $preparacion->solicitud;

        return Preparacion::query()
            ->where('sala_id', $sala->id)
            ->whereKeyNot($preparacion->getKey())
            ->whereHas('solicitud', static fn (Builder $consulta) => $consulta
                ->whereDate('fecha', $solicitud->fecha)
                ->where('hora_inicio', '<', $solicitud->hora_fin)
                ->where('hora_fin', '>', $solicitud->hora_inicio))
            ->with('solicitud')
            ->first();
    }

    private function garantizarTransicion(Preparacion $preparacion, EstadoPreparacion $destino): void
    {
        if (! in_array($destino, $this->transicionesPermitidas()[$preparacion->estado->value], true)) {
            throw TransicionDePreparacionInvalida::de($preparacion->estado, $destino);
        }

        if ($destino === EstadoPreparacion::Preparado && $preparacion->sala_id === null) {
            throw TransicionDePreparacionInvalida::sinSalaAsignada();
        }
    }

    /**
     * El montaje va pendiente -> en preparación -> preparado, y puede
     * volver de preparado a en preparación: se monta con prisa minutos
     * antes de la clase y marcar preparado por error no debe dejar un
     * estado sin salida. Es estado operativo interno, sin consecuencia
     * administrativa.
     *
     * Lo que sigue prohibido es saltarse en preparación.
     *
     * @return array<string, list<EstadoPreparacion>>
     */
    private function transicionesPermitidas(): array
    {
        return [
            EstadoPreparacion::Pendiente->value => [EstadoPreparacion::EnPreparacion],
            EstadoPreparacion::EnPreparacion->value => [EstadoPreparacion::Preparado],
            EstadoPreparacion::Preparado->value => [EstadoPreparacion::EnPreparacion],
        ];
    }

    /**
     * Quién y cuándo terminó el montaje. Se registra al dar por preparado
     * el escenario y se borra al volver atrás: si el montaje ya no está
     * terminado, estos campos no deben seguir diciendo que sí.
     *
     * @return array<string, mixed>
     */
    private function marcasDeMontaje(EstadoPreparacion $destino, User $administrativo): array
    {
        if ($destino !== EstadoPreparacion::Preparado) {
            return ['preparado_por' => null, 'preparado_at' => null];
        }

        return [
            'preparado_por' => $administrativo->id,
            'preparado_at' => now(),
        ];
    }
}
