<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoItemInventario;
use App\Enums\MotivoReposicion;
use App\Exceptions\ReposicionInvalida;
use App\Models\CambioEstadoItem;
use App\Models\ListaDeReposicion;
use App\Models\NecesidadDeReposicion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lista de insumos por pedir o reponer (RF67).
 *
 * Es el soporte de la carta de solicitud de compra que el laboratorio
 * presenta al final del semestre, así que la lista es un documento que se
 * cierra, no una consulta que se recalcula: una vez entregada, sus cifras
 * son las que se entregaron, y corregir un movimiento del historial después
 * no puede cambiarlas.
 *
 * Mientras está en borrador nada se guarda: previsualizar() calcula desde el
 * historial de inventario y las necesidades registradas. cerrar() congela
 * ese resultado en líneas, y de ahí en adelante la pantalla y las
 * exportaciones leen las líneas.
 */
final class ReposicionService
{
    public const POR_PAGINA = 25;

    /**
     * Lo que habría que pedir en un rango de fechas, agrupado por ítem y
     * motivo.
     *
     * Dos orígenes que no se mezclan en una sola consulta a propósito: el
     * historial de inventario sabe de unidades que existieron, y las
     * necesidades saben de cosas que hicieron falta y puede que ni estén en
     * el catálogo. Son decenas de filas, así que se juntan en PHP y se lee
     * sin adivinanzas.
     *
     * @return Collection<int, array{item_inventario_id: int|null, descripcion: string, motivo: MotivoReposicion, cantidad: int}>
     */
    public function previsualizar(string $desde, string $hasta): Collection
    {
        return $this->salidasDeInventario($desde, $hasta)
            ->concat($this->necesidadesRegistradas($desde, $hasta))
            ->sortBy([['motivo', 'asc'], ['descripcion', 'asc']])
            ->values();
    }

    /**
     * Cierra la lista con lo que hay en el rango y la deja inmutable.
     *
     * Congela también la descripción de cada ítem: renombrarlo después no
     * puede cambiar una carta ya entregada.
     */
    public function cerrar(User $actor, ListaDeReposicion $lista, ?string $observaciones = null): ListaDeReposicion
    {
        $this->garantizarPermiso($actor, 'cerrar', $lista);

        if ($lista->estaCerrada()) {
            throw ReposicionInvalida::laListaYaEstaCerrada();
        }

        $lineas = $this->previsualizar(
            $lista->desde->format('Y-m-d'),
            $lista->hasta->format('Y-m-d'),
        );

        if ($lineas->isEmpty()) {
            throw ReposicionInvalida::noHayNadaQuePedir();
        }

        return DB::transaction(function () use ($actor, $lista, $lineas, $observaciones): ListaDeReposicion {
            $lista->lineas()->createMany($lineas->map(static fn (array $linea): array => [
                'item_inventario_id' => $linea['item_inventario_id'],
                'descripcion' => $linea['descripcion'],
                'motivo' => $linea['motivo'],
                'cantidad' => $linea['cantidad'],
            ])->all());

            $lista->observaciones = $observaciones;
            $lista->cerrada_por = $actor->id;
            $lista->cerrada_at = now();
            $lista->save();

            return $lista;
        });
    }

    /**
     * Anota algo que hizo falta y el historial no puede saber: o hace falta
     * más de un ítem que ya existe, o se pidió algo que el laboratorio no
     * tiene.
     */
    public function registrarNecesidad(User $actor, DatosNecesidad $datos): NecesidadDeReposicion
    {
        $this->garantizarPermiso($actor, 'create', NecesidadDeReposicion::class);
        $this->garantizarQueSeSabeQueSePide($datos);
        $this->garantizarCantidad($datos->cantidad);

        $necesidad = new NecesidadDeReposicion([
            'item_inventario_id' => $datos->itemInventarioId,
            'descripcion' => $datos->descripcion,
            'cantidad' => $datos->cantidad,
            'justificacion' => trim($datos->justificacion),
            'fecha' => $datos->fecha ?? now()->toDateString(),
        ]);
        $necesidad->registrada_por = $actor->id;
        $necesidad->save();

        return $necesidad;
    }

    /** @return LengthAwarePaginator<int, ListaDeReposicion> */
    public function listas(int $porPagina = self::POR_PAGINA): LengthAwarePaginator
    {
        return ListaDeReposicion::query()
            ->with('cerradaPor:id,nombre')
            ->withCount('lineas')
            ->orderByDesc('hasta')
            ->orderByDesc('id')
            ->paginate($porPagina);
    }

    /** @return LengthAwarePaginator<int, NecesidadDeReposicion> */
    public function necesidades(string $desde, string $hasta, int $porPagina = self::POR_PAGINA): LengthAwarePaginator
    {
        return NecesidadDeReposicion::query()
            ->entre($desde, $hasta)
            ->with(['item:id,nombre', 'registradaPor:id,nombre'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate($porPagina);
    }

    /**
     * Salidas de unidades del inventario en el rango, agrupadas por ítem y
     * por el estado del que venían (RF66.2): de "operativo" es consumo o
     * retiro, de "defectuoso" es avería.
     *
     * @return Collection<int, array{item_inventario_id: int|null, descripcion: string, motivo: MotivoReposicion, cantidad: int}>
     */
    private function salidasDeInventario(string $desde, string $hasta): Collection
    {
        return CambioEstadoItem::query()
            ->join('items_inventario', 'items_inventario.id', '=', 'cambios_estado_item.item_inventario_id')
            ->where('cambios_estado_item.estado_nuevo', EstadoItemInventario::DadoDeBaja->value)
            ->whereDate('cambios_estado_item.created_at', '>=', $desde)
            ->whereDate('cambios_estado_item.created_at', '<=', $hasta)
            ->groupBy('items_inventario.id', 'items_inventario.nombre', 'cambios_estado_item.estado_anterior')
            ->selectRaw('items_inventario.id as item_id, items_inventario.nombre as nombre, cambios_estado_item.estado_anterior as origen, SUM(cambios_estado_item.cantidad) as unidades')
            ->get()
            ->map(static fn (CambioEstadoItem $fila): array => [
                'item_inventario_id' => (int) $fila->item_id,
                'descripcion' => (string) $fila->nombre,
                'motivo' => MotivoReposicion::deUnaSalida(EstadoItemInventario::tryFrom((string) $fila->origen)),
                'cantidad' => (int) $fila->unidades,
            ]);
    }

    /**
     * Lo anotado a mano en el rango, agrupado por lo que se pide. Se agrupa
     * por descripción y no por ítem porque lo que no está en el catálogo no
     * tiene id con el que agrupar.
     *
     * @return Collection<int, array{item_inventario_id: int|null, descripcion: string, motivo: MotivoReposicion, cantidad: int}>
     */
    private function necesidadesRegistradas(string $desde, string $hasta): Collection
    {
        return NecesidadDeReposicion::query()
            ->entre($desde, $hasta)
            ->with('item:id,nombre')
            ->get()
            ->groupBy(static fn (NecesidadDeReposicion $necesidad): string => $necesidad->queSePide())
            ->map(static fn (Collection $grupo, string $queSePide): array => [
                'item_inventario_id' => $grupo->first()->item_inventario_id,
                'descripcion' => $queSePide,
                'motivo' => MotivoReposicion::Solicitada,
                'cantidad' => (int) $grupo->sum('cantidad'),
            ])
            ->values();
    }

    private function garantizarQueSeSabeQueSePide(DatosNecesidad $datos): void
    {
        if ($datos->itemInventarioId === null && trim((string) $datos->descripcion) === '') {
            throw ReposicionInvalida::noSeSabeQueSePide();
        }
    }

    private function garantizarCantidad(int $cantidad): void
    {
        if ($cantidad < 1) {
            throw ReposicionInvalida::laCantidadEsAlMenosUna();
        }
    }

    /**
     * @param  ListaDeReposicion|class-string  $sobre
     *
     * @throws AuthorizationException
     */
    private function garantizarPermiso(User $actor, string $accion, ListaDeReposicion|string $sobre): void
    {
        if ($actor->cannot($accion, $sobre)) {
            throw new AuthorizationException('El usuario no tiene permiso para esta acción sobre la lista de reposición.');
        }
    }
}
