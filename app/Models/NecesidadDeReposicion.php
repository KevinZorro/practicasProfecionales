<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\NecesidadDeReposicionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Algo que hizo falta y no salía del historial (RF67).
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

    /** @param Builder<$this> $consulta */
    public function scopeEntre(Builder $consulta, string $desde, string $hasta): void
    {
        $consulta->whereBetween('fecha', [$desde, $hasta]);
    }
}
