<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\NecesidadDeReposicionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Algo que hizo falta y no salía del historial (RF67).
 *
 * Es un **saldo pendiente**, no un hecho de un periodo: vive hasta que
 * alguien la marca como atendida, y mientras tanto entra en todos los
 * borradores. Los movimientos de inventario son lo contrario —un flujo del
 * periodo— y por eso sí se filtran por rango de fechas.
 *
 * Dos casos, y el mismo registro sirve para los dos:
 *
 * - Hace falta más de algo que ya está en el catálogo: item_inventario_id
 *   apunta al ítem.
 * - Se pidió algo que el laboratorio no tiene —el ejemplo del cliente es una
 *   pila CR2032 para el control del desfibrilador—: item_inventario_id queda
 *   nulo y la descripción dice qué es.
 *
 * No se crea un ítem del catálogo con cero unidades para representar lo
 * segundo: aparecería en la disponibilidad y en el formulario del docente
 * como si el laboratorio lo tuviera, que es justo lo contrario de lo que
 * pasa. Un CHECK garantiza que cada fila diga qué pide, por una vía o por la
 * otra.
 */
class NecesidadDeReposicion extends Model
{
    /** @use HasFactory<NecesidadDeReposicionFactory> */
    use HasFactory;

    protected $table = 'necesidades_reposicion';

    /**
     * registrada_por queda fuera: lo pone el Service con quien lo pidió.
     *
     * @var list<string>
     */
    protected $fillable = [
        'item_inventario_id',
        'descripcion',
        'cantidad',
        'justificacion',
        'fecha',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'fecha' => 'date',
            'atendida_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ItemInventario, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemInventario::class, 'item_inventario_id');
    }

    /** @return BelongsTo<User, $this> */
    public function registradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrada_por');
    }

    /** @return BelongsTo<User, $this> */
    public function atendidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atendida_por');
    }

    /**
     * Listas cerradas en las que se pidió. Más de una significa que se
     * pidió y no llegó.
     *
     * @return BelongsToMany<ListaDeReposicion, $this>
     */
    public function listas(): BelongsToMany
    {
        return $this->belongsToMany(
            ListaDeReposicion::class,
            'lista_reposicion_necesidad',
            'necesidad_reposicion_id',
            'lista_reposicion_id',
        );
    }

    /** Qué hay que comprar: el nombre del ítem, o la descripción libre. */
    public function queSePide(): string
    {
        return $this->item?->nombre ?? (string) $this->descripcion;
    }

    /** Lo pedido que todavía no existe en el catálogo. */
    public function esDeFueraDelCatalogo(): bool
    {
        return $this->item_inventario_id === null;
    }

    public function estaAtendida(): bool
    {
        return $this->atendida_at !== null;
    }

    /**
     * Lo que sigue haciendo falta, sin importar cuándo se anotó.
     *
     * Las necesidades no se filtran por periodo como los movimientos de
     * inventario: si la pila no llegó, el semestre siguiente sigue
     * haciendo falta.
     *
     * @param  Builder<$this>  $consulta
     */
    public function scopePendientes(Builder $consulta): void
    {
        $consulta->whereNull('atendida_at');
    }

    /** @param Builder<$this> $consulta */
    public function scopeEntre(Builder $consulta, string $desde, string $hasta): void
    {
        $consulta->whereBetween('fecha', [$desde, $hasta]);
    }
}
