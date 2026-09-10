<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoItemInventario;
use App\Enums\NivelFidelidad;
use App\Enums\TipoItemInventario;
use App\Exceptions\InventarioInvalido;
use App\Models\ItemInventario;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Gestión del inventario de simuladores y equipos (RF38-RF40).
 *
 * El nivel de fidelidad solo lo registra el ADMIN (RF39). La restricción es
 * de campo: el resto de columnas del mismo ítem las editan administrativos
 * y coordinadores. Se garantiza en dos capas: aquí, consultando la Policy
 * antes de tocar la columna, y en el modelo, que la deja fuera de fillable
 * para que ninguna asignación masiva pueda saltarse esta comprobación.
 */
final class InventarioService
{
    public function crear(User $actor, DatosItemInventario $datos): ItemInventario
    {
        $this->garantizarFidelidadCoherente($datos->tipo, $datos->nivelFidelidad);

        $item = new ItemInventario;
        $item->fill($this->atributosMasivos($datos));
        $this->aplicarNivelFidelidad($actor, $item, $datos->nivelFidelidad);
        $item->save();

        return $item;
    }

    public function actualizar(User $actor, ItemInventario $item, DatosItemInventario $datos): ItemInventario
    {
        $this->garantizarFidelidadCoherente($datos->tipo, $datos->nivelFidelidad);

        $item->fill($this->atributosMasivos($datos));
        $this->aplicarNivelFidelidad($actor, $item, $datos->nivelFidelidad);
        $item->save();

        return $item;
    }

    /**
     * Baja lógica: el ítem no se borra porque el histórico de solicitudes lo
     * referencia y sus llaves foráneas son restrictivas.
     */
    public function darDeBaja(ItemInventario $item): ItemInventario
    {
        $item->update([
            'estado' => EstadoItemInventario::Baja,
            'activo' => false,
        ]);

        return $item;
    }

    /**
     * Unidades libres de un ítem en una franja: las totales menos las
     * comprometidas en solicitudes aprobadas que se pisen con ella.
     */
    public function disponibilidadEnFranja(
        ItemInventario $item,
        string $fecha,
        string $horaInicio,
        string $horaFin,
    ): int {
        if (! $this->estaOperativo($item)) {
            return 0;
        }

        $comprometido = $this->comprometidoPorItem([$item->id], $fecha, $horaInicio, $horaFin);

        return max(0, $item->cantidad_total - ($comprometido[$item->id] ?? 0));
    }

    /**
     * Lo mismo para varios ítems, con una sola consulta de lo comprometido.
     *
     * @param  Collection<int, ItemInventario>  $items
     * @return array<int, int> id del ítem => unidades libres
     */
    public function disponibilidadDeVarios(
        Collection $items,
        string $fecha,
        string $horaInicio,
        string $horaFin,
    ): array {
        $comprometido = $this->comprometidoPorItem($items->modelKeys(), $fecha, $horaInicio, $horaFin);

        return $items->mapWithKeys(fn (ItemInventario $item): array => [
            $item->id => $this->estaOperativo($item)
                ? max(0, $item->cantidad_total - ($comprometido[$item->id] ?? 0))
                : 0,
        ])->all();
    }

    /**
     * Listado filtrable del inventario.
     *
     * @return Collection<int, ItemInventario>
     */
    public function listar(
        ?TipoItemInventario $tipo = null,
        ?EstadoItemInventario $estado = null,
        ?NivelFidelidad $nivelFidelidad = null,
    ): Collection {
        return ItemInventario::query()
            ->when($tipo instanceof TipoItemInventario, fn ($consulta) => $consulta->where('tipo', $tipo))
            ->when($estado instanceof EstadoItemInventario, fn ($consulta) => $consulta->where('estado', $estado))
            ->when($nivelFidelidad instanceof NivelFidelidad, fn ($consulta) => $consulta->where('nivel_fidelidad', $nivelFidelidad))
            ->orderBy('tipo')
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Todo menos el nivel de fidelidad, que nunca viaja por fill().
     *
     * @return array<string, mixed>
     */
    private function atributosMasivos(DatosItemInventario $datos): array
    {
        return [
            'nombre' => $datos->nombre,
            'tipo' => $datos->tipo,
            'cantidad_total' => $datos->cantidadTotal,
            'descripcion' => $datos->descripcion,
            'estado' => $datos->estado,
            'activo' => $datos->activo,
        ];
    }

    /**
     * Solo el ADMIN cambia el nivel de fidelidad (RF39). Si el valor que
     * llega es el que ya tenía, no hay cambio que autorizar: un formulario
     * que devuelve el valor actual sin tocarlo no debe fallarle a un
     * administrativo.
     */
    private function aplicarNivelFidelidad(User $actor, ItemInventario $item, ?NivelFidelidad $nivel): void
    {
        if ($nivel === $item->nivel_fidelidad) {
            return;
        }

        if ($actor->cannot('editarNivelFidelidad', $item)) {
            throw InventarioInvalido::nivelDeFidelidadReservadoAlAdmin();
        }

        $item->nivel_fidelidad = $nivel;
    }

    /**
     * Un equipo básico con fidelidad alta es un dato inválido: el nivel solo
     * tiene sentido en simuladores.
     */
    private function garantizarFidelidadCoherente(TipoItemInventario $tipo, ?NivelFidelidad $nivel): void
    {
        if ($nivel instanceof NivelFidelidad && ! $tipo->admiteNivelFidelidad()) {
            throw InventarioInvalido::nivelDeFidelidadSoloEnSimuladores($tipo);
        }
    }

    private function estaOperativo(ItemInventario $item): bool
    {
        return $item->activo && $item->estado === EstadoItemInventario::Disponible;
    }

    /**
     * Unidades comprometidas por ítem en solicitudes aprobadas que se pisan
     * con la franja. El criterio de solapamiento es el scope
     * queSeSolapanCon de Solicitud, el mismo que usa la asignación de sala.
     *
     * @param  list<int>  $itemIds
     * @return array<int, int>
     */
    private function comprometidoPorItem(array $itemIds, string $fecha, string $horaInicio, string $horaFin): array
    {
        if ($itemIds === []) {
            return [];
        }

        return Solicitud::query()
            ->aprobadas()
            ->queSeSolapanCon($fecha, $horaInicio, $horaFin)
            ->join('solicitud_item', 'solicitud_item.solicitud_id', '=', 'solicitudes.id')
            ->whereIn('solicitud_item.item_inventario_id', $itemIds)
            ->groupBy('solicitud_item.item_inventario_id')
            ->selectRaw('solicitud_item.item_inventario_id as item_id, SUM(solicitud_item.cantidad) as total')
            ->pluck('total', 'item_id')
            ->map(static fn (int|string $total): int => (int) $total)
            ->all();
    }
}
