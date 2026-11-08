<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoPreparacion;
use App\Enums\NivelFidelidad;
use App\Enums\TipoItemInventario;
use App\Events\SalaAsignada;
use App\Exceptions\AsignacionDeSalasInvalida;
use App\Exceptions\SalaInsuficiente;
use App\Exceptions\SalaOcupada;
use App\Exceptions\TransicionDePreparacionInvalida;
use App\Models\ItemInventario;
use App\Models\Preparacion;
use App\Models\Sala;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Montaje físico de los escenarios aprobados (RF36-RF37).
 *
 * La preparación nace al aprobar la solicitud, con sala nula. Este servicio
 * cubre lo que pasa después: el administrativo asigna la sala y alista el
 * material, minutos antes de la clase.
 */
final class PreparacionService
{
    /** Las que se pueden unir abriendo las cortinas (P16). */
    public const MAXIMO_DE_SALAS = 3;

    /**
     * Se llama desde SolicitudService al aprobar, dentro de su transacción.
     */
    public function crearDesdeSolicitud(Solicitud $solicitud): Preparacion
    {
        // La sala llega nula: la asigna el administrativo el día de la
        // práctica, no el coordinador al aprobar (§4.5 del documento).
        $preparacion = $solicitud->preparacion()->create([
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

    /**
     * El administrativo elige las salas (RF36): de una a tres, que abriendo
     * las cortinas son un solo salón (P16). El grupo tiene que caber en la
     * capacidad de todas juntas (P11). Si las salas cambian, el docente
     * recibe un correo con las nuevas; si se vuelven a elegir las mismas, no.
     *
     * Comprobar que cada sala está libre y escribir van en una transacción
     * con dos bloqueos. Primero la sesión: dos administrativos que eligen a
     * la vez salas para el mismo montaje se turnan, y el segundo reemplaza la
     * elección del primero en vez de sumarle sus salas; tampoco cambia la
     * franja a mitad de camino por una reprogramación. Después las salas, en
     * orden de id: dos administrativos que asignan la misma sala a franjas
     * que se cruzan se turnan, y el segundo ya ve la reserva del primero
     * (B3.11). Es el mismo criterio de las transiciones de solicitud (QA-02).
     *
     * @param  list<int>  $salaIds
     */
    public function asignarSalas(Preparacion $preparacion, array $salaIds): Preparacion
    {
        $salaIds = array_values(array_unique($salaIds));
        sort($salaIds);

        if ($salaIds === [] || count($salaIds) > self::MAXIMO_DE_SALAS) {
            throw AsignacionDeSalasInvalida::cantidad(self::MAXIMO_DE_SALAS);
        }

        // Las salas que tenía antes, o null si se volvieron a elegir las mismas.
        $anteriores = DB::transaction(function () use ($preparacion, $salaIds): ?array {
            $sesion = Solicitud::query()->whereKey($preparacion->solicitud_id)->lockForUpdate()->firstOrFail();
            $preparacion->setRelation('solicitud', $sesion);

            /** @var list<int> $anteriores */
            $anteriores = $preparacion->salas()->pluck('salas.id')->sort()->values()->all();

            $salas = Sala::query()->whereIn('id', $salaIds)->orderBy('id')->lockForUpdate()->get();

            if ($salas->count() !== count($salaIds)) {
                throw AsignacionDeSalasInvalida::salaInexistente();
            }

            if ((int) $salas->sum('capacidad') < $sesion->cantidad_estudiantes) {
                throw SalaInsuficiente::para($salas, $sesion->cantidad_estudiantes);
            }

            foreach ($salas as $sala) {
                $conflicto = $this->preparacionSolapada($preparacion, $sala);

                if ($conflicto instanceof Preparacion) {
                    throw SalaOcupada::por($sala, $conflicto);
                }
            }

            if ($anteriores === $salaIds) {
                return null;
            }

            $preparacion->salas()->sync($salaIds);

            return $anteriores;
        });

        if ($anteriores === null) {
            return $preparacion;
        }

        $preparacion->load('salas');

        // Después de confirmar: no sale un correo por una asignación que se deshizo.
        SalaAsignada::dispatch($preparacion, esCambio: $anteriores !== []);

        return $preparacion;
    }

    /**
     * Lo que requiere al ingeniero: los simuladores de alta fidelidad (RF72,
     * D10). El ingeniero no tiene cuenta; el administrativo marca el ítem
     * cuando él termina, como cualquier otro.
     */
    public function requiereIngeniero(ItemInventario $item): bool
    {
        return $item->tipo === TipoItemInventario::Simulador && $item->nivel_fidelidad === NivelFidelidad::Alta;
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
            // Hoy una preparación solo nace al aprobar, y aprobada es un
            // estado terminal, así que este filtro no descarta nada. Se deja
            // explícito para que el tablero garantice por sí mismo lo que el
            // RF36 pide —solo escenarios aprobados— y no dependa de que esa
            // regla siga viviendo en SolicitudService.
            ->whereHas('solicitud', static fn (Builder $consulta) => $consulta->aprobadas())
            ->with(['solicitud.docente', 'solicitud.docenteQueDicta', 'solicitud.materia', 'solicitud.casoClinico', 'solicitud.estudiantesPresentes:users.id', 'salas', 'items'])
            ->orderBy(Solicitud::select('hora_inicio')->whereColumn('solicitudes.id', 'preparaciones.solicitud_id'))
            ->get();
    }

    /**
     * Salas que quedan libres para esta preparación (RF37).
     *
     * El criterio de solapamiento no se repite aquí: es el mismo scope
     * queSeSolapanCon de Solicitud que usan la validación de asignarSalas y
     * el cálculo de disponibilidad de inventario. Si el criterio cambia,
     * cambia en los tres a la vez.
     *
     * Las salas vinculadas al escenario van primero y vienen marcadas en
     * "del_escenario", pero no se filtra por ellas: un vínculo sin llenar no
     * puede dejar una clase sin sala (D14 de docs/trazabilidad.md).
     *
     * @return Collection<int, Sala>
     */
    public function salasLibresPara(Preparacion $preparacion): Collection
    {
        $solicitud = $preparacion->solicitud;

        // Todas las libres, también las que solas no alcanzan: se pueden unir
        // hasta tres (P16), y la capacidad la comprueba asignarSalas.
        return Sala::query()
            ->activas()
            ->withExists(['casosClinicos as del_escenario' => static fn (Builder $caso) => $caso->whereKey($solicitud->caso_clinico_id)])
            ->whereDoesntHave('preparaciones', fn (Builder $consulta) => $consulta
                ->whereKeyNot($preparacion->getKey())
                ->whereHas('solicitud', static fn (Builder $suya) => $suya->queSeSolapanCon(
                    $solicitud->fecha->format('Y-m-d'),
                    $solicitud->hora_inicio,
                    $solicitud->hora_fin,
                )))
            ->orderByDesc('del_escenario')
            ->orderBy('nombre')
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
            ->whereHas('salas', static fn (Builder $salas) => $salas->whereKey($sala->id))
            ->whereHas('solicitud', static fn (Builder $consulta) => $consulta->whereBetween('fecha', [$desde, $hasta]))
            ->with(['solicitud.docente', 'solicitud.materia'])
            ->get();
    }

    /**
     * Otra preparación en la misma sala con la franja pisándose. El criterio
     * de solapamiento vive en el scope queSeSolapanCon de Solicitud, que
     * comparte con el cálculo de disponibilidad de inventario.
     */
    private function preparacionSolapada(Preparacion $preparacion, Sala $sala): ?Preparacion
    {
        $solicitud = $preparacion->solicitud;

        return Preparacion::query()
            ->whereHas('salas', static fn (Builder $salas) => $salas->whereKey($sala->id))
            ->whereKeyNot($preparacion->getKey())
            ->whereHas('solicitud', static fn (Builder $consulta) => $consulta->queSeSolapanCon(
                $solicitud->fecha->format('Y-m-d'),
                $solicitud->hora_inicio,
                $solicitud->hora_fin,
            ))
            ->with(['solicitud.docente', 'solicitud.casoClinico'])
            ->first();
    }

    private function garantizarTransicion(Preparacion $preparacion, EstadoPreparacion $destino): void
    {
        if (! in_array($destino, $this->transicionesPermitidas()[$preparacion->estado->value], true)) {
            throw TransicionDePreparacionInvalida::de($preparacion->estado, $destino);
        }

        if ($destino === EstadoPreparacion::Preparado && $preparacion->salas()->doesntExist()) {
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
