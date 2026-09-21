<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoItemInventario;
use App\Enums\EstadoSolicitud;
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

        return DB::transaction(function () use ($actor, $datos): ItemInventario {
            $item = new ItemInventario;
            $item->fill($this->atributosMasivos($datos));
            // Todas las unidades nacen operativas (RF66.5).
            $item->cantidad_total = $datos->cantidadTotal;
            $item->cantidad_operativa = $datos->cantidadTotal;
            $item->cantidad_en_revision = 0;
            $item->cantidad_defectuosa = 0;
            $this->aplicarNivelFidelidad($actor, $item, $datos->nivelFidelidad);
            $item->save();

            // Asiento de apertura: a partir de aquí el historial reconstruye
            // los contadores por sí solo, que es lo que comprueba el test de
            // reconciliación.
            if ($datos->cantidadTotal > 0) {
                $this->anotar($actor, $item, null, EstadoItemInventario::Operativo, $datos->cantidadTotal, 'Alta en el inventario.');
            }

            return $item;
        });
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
     * Mueve N unidades de un estado funcional a otro y deja constancia de
     * cuántas, quién y por qué (RF66).
     *
     * Los contadores viven en el ítem y el movimiento se apunta en el
     * historial; las dos escrituras van en la misma transacción para que no
     * puedan divergir, y la fila del ítem se bloquea mientras tanto: dos
     * administrativas marcando desde el celular leerían el mismo saldo y
     * restarían de más.
     */
    public function cambiarEstado(
        User $actor,
        ItemInventario $item,
        EstadoItemInventario $origen,
        EstadoItemInventario $destino,
        int $cantidad,
        string $motivo,
    ): ItemInventario {
        $this->garantizarPermisoDeMovimiento($actor, $item, $destino);
        $this->garantizarTransicion($origen, $destino);

        return $this->mover($actor, $item, $origen, $destino, $cantidad, $motivo);
    }

    /**
     * Descarta N unidades defectuosas. Es definitivo: salen del total y no
     * vuelven.
     */
    public function darDeBaja(User $actor, ItemInventario $item, int $cantidad, string $motivo): ItemInventario
    {
        return $this->cambiarEstado(
            $actor,
            $item,
            EstadoItemInventario::Defectuoso,
            EstadoItemInventario::DadoDeBaja,
            $cantidad,
            $motivo,
        );
    }

    /**
     * Saca N unidades operativas del inventario por algo que no es una
     * avería: gasto en prácticas, pérdida o corrección de conteo.
     *
     * Deliberadamente fuera de la tabla de transiciones: el flujo del RF66
     * describe cómo se avería una pieza, y esto no es eso. Se registra igual
     * que una baja —el historial distingue una de otra por el estado de
     * origen—, porque bajar el total nunca puede ser silencioso.
     *
     * PENDIENTE de confirmar con el cliente: esto lo puede hacer quien
     * gestiona el inventario, no solo coordinación, porque contar gasas
     * gastadas es trabajo diario. Descartar una pieza averiada sí sigue
     * siendo decisión de coordinación.
     */
    public function retirarUnidades(User $actor, ItemInventario $item, int $cantidad, string $motivo): ItemInventario
    {
        $this->garantizarPermiso($actor, $item, 'cambiarEstadoFuncional');

        return $this->mover(
            $actor,
            $item,
            EstadoItemInventario::Operativo,
            EstadoItemInventario::DadoDeBaja,
            $cantidad,
            $motivo,
        );
    }

    /** Entran N unidades nuevas al inventario, siempre operativas. */
    public function reponerUnidades(User $actor, ItemInventario $item, int $cantidad, string $motivo): ItemInventario
    {
        $this->garantizarPermiso($actor, $item, 'cambiarEstadoFuncional');

        return $this->mover($actor, $item, null, EstadoItemInventario::Operativo, $cantidad, $motivo);
    }

    /**
     * Unidades libres de un ítem en una franja: las **operativas** menos las
     * comprometidas en solicitudes aprobadas que se pisen con ella.
     *
     * Solo cuentan las operativas: las que están en revisión, defectuosas o
     * dadas de baja no se pueden llevar a una práctica (RF66).
     */
    public function disponibilidadEnFranja(
        ItemInventario $item,
        string $fecha,
        string $horaInicio,
        string $horaFin,
    ): int {
        $comprometido = $this->comprometidoPorItem([$item->id], $fecha, $horaInicio, $horaFin);

        return $this->libres($item, $comprometido[$item->id] ?? 0);
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
            $item->id => $this->libres($item, $comprometido[$item->id] ?? 0),
        ])->all();
    }

    /**
     * Prácticas ya aprobadas que se quedaron sin unidades suficientes
     * porque alguna dejó de servir después de aprobarlas (RF66.2).
     *
     * Es solo detección: qué hacer con una práctica aprobada —avisar al
     * docente, volver a resolverla, buscar sustituto— es decisión del
     * cliente, así que aquí no se toca nada.
     *
     * Una sola consulta. El criterio de solapamiento es el mismo que el de
     * Solicitud::scopeQueSeSolapanCon(), repetido aquí porque la subconsulta
     * compara contra las columnas de la solicitud de fuera y no contra
     * literales; si ese criterio cambia, hay que cambiarlo en los dos sitios.
     *
     * @return list<int> ids de solicitud
     */
    public function solicitudesAprobadasSinCobertura(): array
    {
        return Solicitud::query()
            ->aprobadas()
            ->whereDate('solicitudes.fecha', '>=', now()->toDateString())
            ->whereExists(fn ($consulta) => $consulta
                ->from('solicitud_item')
                ->join('items_inventario', 'items_inventario.id', '=', 'solicitud_item.item_inventario_id')
                ->whereColumn('solicitud_item.solicitud_id', 'solicitudes.id')
                ->whereRaw('items_inventario.cantidad_operativa < (
                    SELECT COALESCE(SUM(otras.cantidad), 0)
                    FROM solicitud_item AS otras
                    JOIN solicitudes AS s2 ON s2.id = otras.solicitud_id
                    WHERE otras.item_inventario_id = solicitud_item.item_inventario_id
                      AND s2.estado = ?
                      AND s2.fecha = solicitudes.fecha
                      AND s2.hora_inicio < solicitudes.hora_fin
                      AND s2.hora_fin > solicitudes.hora_inicio
                )', [EstadoSolicitud::Aprobada->value]))
            ->pluck('solicitudes.id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();
    }

    /**
     * Listado filtrable del inventario (RF38).
     *
     * "soloSinFidelidad" busca los simuladores que todavía esperan que el
     * ADMIN les asigne el nivel: un administrativo puede dar de alta el
     * maniquí y el ADMIN completarlo después (RF39), así que ese estado
     * intermedio hay que poder encontrarlo.
     *
     * "soloConUnidadesNoOperativas" es lo que la administrativa necesita
     * para encontrar rápido lo que hay que mirar (RF66.2).
     *
     * @return LengthAwarePaginator<int, ItemInventario>
     */
    public function listar(
        ?TipoItemInventario $tipo = null,
        ?EstadoItemInventario $estado = null,
        ?NivelFidelidad $nivelFidelidad = null,
        ?string $busqueda = null,
        bool $soloSinFidelidad = false,
        bool $soloConUnidadesNoOperativas = false,
        int $porPagina = self::POR_PAGINA,
    ): LengthAwarePaginator {
        return ItemInventario::query()
            // El historial se pinta junto a cada ítem (RF66): sin esto, una
            // página de 15 ítems dispara 31 consultas.
            ->with(['cambiosDeEstado.registradoPor:id,nombre'])
            ->when($tipo instanceof TipoItemInventario, fn (Builder $c) => $c->where('tipo', $tipo))
            // Filtrar por estado ahora significa "tiene unidades en ese
            // estado", porque un mismo ítem puede tener unidades en varios.
            ->when($estado instanceof EstadoItemInventario, fn (Builder $c) => $c->conUnidadesEn($estado))
            ->when($soloConUnidadesNoOperativas, fn (Builder $c) => $c->conUnidadesNoOperativas())
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
     * las cantidades, que solo se mueven por cambiarEstado(),
     * retirarUnidades() y reponerUnidades(): si vinieran del formulario se
     * colarían sin motivo, sin responsable y sin respetar el flujo (RF66).
     *
     * @return array<string, mixed>
     */
    private function atributosMasivos(DatosItemInventario $datos): array
    {
        return [
            'nombre' => $datos->nombre,
            'tipo' => $datos->tipo,
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

    private function libres(ItemInventario $item, int $comprometido): int
    {
        return $item->activo ? max(0, $item->cantidad_operativa - $comprometido) : 0;
    }

    /**
     * El único sitio que mueve unidades entre estados.
     *
     * Vuelve a leer el ítem bajo bloqueo de fila: sin eso, dos cambios
     * simultáneos sobre el mismo ítem leen el mismo saldo y lo dejan por
     * debajo de cero. El CHECK de la base lo cazaría, pero fallando con un
     * error de base de datos en vez de con un mensaje del dominio.
     */
    private function mover(
        User $actor,
        ItemInventario $item,
        ?EstadoItemInventario $origen,
        EstadoItemInventario $destino,
        int $cantidad,
        string $motivo,
    ): ItemInventario {
        $this->garantizarCantidad($cantidad);
        $this->garantizarMotivo($motivo);

        return DB::transaction(function () use ($actor, $item, $origen, $destino, $cantidad, $motivo): ItemInventario {
            $bloqueado = ItemInventario::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            if ($origen instanceof EstadoItemInventario) {
                $this->garantizarUnidadesSuficientes($bloqueado, $origen, $cantidad);
                $bloqueado->{$origen->columnaDeCantidad()} -= $cantidad;
            }

            $columnaDestino = $destino->columnaDeCantidad();

            if ($columnaDestino === null) {
                // Las unidades salen del inventario: bajan del total.
                $bloqueado->cantidad_total -= $cantidad;
            } else {
                $bloqueado->{$columnaDestino} += $cantidad;
            }

            if ($origen === null) {
                $bloqueado->cantidad_total += $cantidad;
            }

            // Un ítem sin unidades sale del catálogo, y vuelve si le reponen.
            // El registro nunca se borra: el histórico de solicitudes lo
            // referencia con llaves foráneas restrictivas.
            $bloqueado->activo = $bloqueado->cantidad_total > 0;
            $bloqueado->save();

            $this->anotar($actor, $bloqueado, $origen, $destino, $cantidad, $motivo);

            $item->setRawAttributes($bloqueado->getAttributes(), true);

            return $item;
        });
    }

    private function anotar(
        User $actor,
        ItemInventario $item,
        ?EstadoItemInventario $origen,
        EstadoItemInventario $destino,
        int $cantidad,
        string $motivo,
    ): void {
        CambioEstadoItem::create([
            'item_inventario_id' => $item->id,
            'cantidad' => $cantidad,
            'estado_anterior' => $origen,
            'estado_nuevo' => $destino,
            'motivo' => trim($motivo),
            'registrado_por' => $actor->id,
        ]);
    }

    /**
     * La baja pide coordinación; el resto de movimientos, gestión de
     * inventario (RF66.4).
     */
    private function garantizarPermisoDeMovimiento(User $actor, ItemInventario $item, EstadoItemInventario $destino): void
    {
        $this->garantizarPermiso($actor, $item, $destino->esDefinitivo() ? 'darDeBaja' : 'cambiarEstadoFuncional');
    }

    private function garantizarPermiso(User $actor, ItemInventario $item, string $permiso): void
    {
        if ($actor->cannot($permiso, $item)) {
            throw new AuthorizationException(
                'El usuario no tiene permiso para mover unidades de este ítem.',
            );
        }
    }

    private function garantizarTransicion(EstadoItemInventario $origen, EstadoItemInventario $destino): void
    {
        if ($origen->esDefinitivo()) {
            throw InventarioInvalido::laBajaEsDefinitiva();
        }

        if (! $origen->permitePasarA($destino)) {
            throw InventarioInvalido::transicionDeEstadoInvalida($origen, $destino);
        }
    }

    private function garantizarUnidadesSuficientes(ItemInventario $item, EstadoItemInventario $origen, int $cantidad): void
    {
        $disponibles = $item->cantidadEn($origen);

        if ($cantidad > $disponibles) {
            throw InventarioInvalido::noHayTantasUnidades($origen, $disponibles, $cantidad);
        }
    }

    private function garantizarCantidad(int $cantidad): void
    {
        if ($cantidad < 1) {
            throw InventarioInvalido::laCantidadEsAlMenosUna();
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
