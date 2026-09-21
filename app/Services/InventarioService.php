<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoItemInventario;
use App\Enums\NivelFidelidad;
use App\Enums\TipoItemInventario;
use App\Exceptions\InventarioInvalido;
use App\Models\CambioEstadoItem;
use App\Models\ItemInventario;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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
    public const POR_PAGINA = 15;

    public function crear(User $actor, DatosItemInventario $datos): ItemInventario
    {
        $this->garantizarFidelidadCoherente($datos->tipo, $datos->nivelFidelidad);

        $item = new ItemInventario;
        $item->fill($this->atributosMasivos($datos));
        // Todo ítem nace operativo (RF66.5). Se asigna aquí y no solo con el
        // default de la base: si no, el modelo recién creado vuelve con el
        // atributo sin poblar y quien lea $item->estado se encuentra un null.
        $item->estado = EstadoItemInventario::Operativo;
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
     * Cambia el estado funcional de un ítem y deja constancia de quién y por
     * qué (RF66).
     *
     * El estado actual se guarda en el ítem —lo lee cada cálculo de
     * disponibilidad— y el cambio se apunta en el historial. Las dos
     * escrituras van en la misma transacción para que no puedan divergir.
     */
    public function cambiarEstado(
        User $actor,
        ItemInventario $item,
        EstadoItemInventario $destino,
        string $motivo,
    ): ItemInventario {
        $this->garantizarPermisoDeEstado($actor, $item, $destino);
        $this->garantizarTransicion($item, $destino);
        $this->garantizarMotivo($motivo);

        return DB::transaction(function () use ($actor, $item, $destino, $motivo): ItemInventario {
            $anterior = $item->estado;

            // estado está fuera de fillable a propósito, así que se asigna
            // a mano: es la única puerta por la que pasa.
            $item->estado = $destino;
            // La baja lo saca además del catálogo. El registro no se borra:
            // el histórico de solicitudes lo referencia con llaves foráneas
            // restrictivas.
            $item->activo = ! $destino->esDefinitivo();
            $item->save();

            CambioEstadoItem::create([
                'item_inventario_id' => $item->id,
                'estado_anterior' => $anterior,
                'estado_nuevo' => $destino,
                'motivo' => trim($motivo),
                'registrado_por' => $actor->id,
            ]);

            return $item;
        });
    }

    /**
     * Baja lógica: el ítem no se borra porque el histórico de solicitudes lo
     * referencia y sus llaves foráneas son restrictivas.
     */
    public function darDeBaja(User $actor, ItemInventario $item, string $motivo): ItemInventario
    {
        return $this->cambiarEstado($actor, $item, EstadoItemInventario::DadoDeBaja, $motivo);
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
     * Listado filtrable del inventario (RF38).
     *
     * "soloSinFidelidad" busca los simuladores que todavía esperan que el
     * ADMIN les asigne el nivel: un administrativo puede dar de alta el
     * maniquí y el ADMIN completarlo después (RF39), así que ese estado
     * intermedio hay que poder encontrarlo.
     *
     * @return LengthAwarePaginator<int, ItemInventario>
     */
    public function listar(
        ?TipoItemInventario $tipo = null,
        ?EstadoItemInventario $estado = null,
        ?NivelFidelidad $nivelFidelidad = null,
        ?string $busqueda = null,
        bool $soloSinFidelidad = false,
        int $porPagina = self::POR_PAGINA,
    ): LengthAwarePaginator {
        return ItemInventario::query()
            // El historial se pinta junto a cada ítem (RF66): sin esto, una
            // página de 15 ítems dispara 31 consultas.
            ->with(['cambiosDeEstado.registradoPor:id,nombre'])
            ->when($tipo instanceof TipoItemInventario, fn (Builder $c) => $c->where('tipo', $tipo))
            ->when($estado instanceof EstadoItemInventario, fn (Builder $c) => $c->where('estado', $estado))
            ->when($nivelFidelidad instanceof NivelFidelidad, fn (Builder $c) => $c->where('nivel_fidelidad', $nivelFidelidad))
            ->when($soloSinFidelidad, fn (Builder $c) => $c
                ->where('tipo', TipoItemInventario::Simulador)
                ->whereNull('nivel_fidelidad'))
            ->when(
                is_string($busqueda) && trim($busqueda) !== '',
                fn (Builder $c) => $c->whereRaw('LOWER(nombre) LIKE ?', ['%'.mb_strtolower(trim((string) $busqueda)).'%']),
            )
            ->orderBy('tipo')
            ->orderBy('nombre')
            ->paginate($porPagina);
    }

    /**
     * Todo menos el nivel de fidelidad, que nunca viaja por fill(), y menos
     * el estado funcional, que solo cambia por cambiarEstado(): si viniera
     * del formulario se colaría sin motivo, sin responsable y sin respetar
     * el flujo (RF66).
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
        return $item->activo && $item->estado->permiteUso();
    }

    /**
     * La baja pide coordinación; el resto de transiciones, gestión de
     * inventario (RF66.4).
     */
    private function garantizarPermisoDeEstado(User $actor, ItemInventario $item, EstadoItemInventario $destino): void
    {
        $permiso = $destino->esDefinitivo() ? 'darDeBaja' : 'cambiarEstadoFuncional';

        if ($actor->cannot($permiso, $item)) {
            throw new AuthorizationException(
                sprintf('El usuario no tiene permiso para dejar este ítem en "%s".', $destino->etiqueta()),
            );
        }
    }

    private function garantizarTransicion(ItemInventario $item, EstadoItemInventario $destino): void
    {
        if ($item->estado->esDefinitivo()) {
            throw InventarioInvalido::laBajaEsDefinitiva();
        }

        if (! $item->estado->permitePasarA($destino)) {
            throw InventarioInvalido::transicionDeEstadoInvalida($item->estado, $destino);
        }
    }

    private function garantizarMotivo(string $motivo): void
    {
        if (trim($motivo) === '') {
            throw InventarioInvalido::elMotivoEsObligatorio();
        }
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
