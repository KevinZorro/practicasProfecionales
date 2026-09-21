<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoItemInventario;
use Database\Factories\CambioEstadoItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El movimiento de N unidades de un ítem entre dos estados funcionales
 * (RF66).
 *
 * El historial es de solo añadir: cada movimiento deja una fila con cuántas
 * unidades, de qué estado a cuál, con su motivo y su responsable, y no se
 * edita ni se borra. Saber qué pasó con una pieza es el objetivo del
 * requerimiento, no un efecto secundario.
 *
 * Los dos extremos son nulos o de baja según el movimiento:
 *
 * - `estado_anterior` nulo: unidades que entran al inventario (alta o
 *   reposición).
 * - `estado_nuevo` dado de baja: unidades que salen. Si venían de
 *   "defectuoso" es la baja del RF66; si venían de "operativo" es una
 *   salida del inventario que no es avería —gasto en prácticas, pérdida,
 *   corrección de conteo—, y el motivo lo explica.
 */
class CambioEstadoItem extends Model
{
    /** @use HasFactory<CambioEstadoItemFactory> */
    use HasFactory;

    protected $table = 'cambios_estado_item';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'item_inventario_id',
        'cantidad',
        'estado_anterior',
        'estado_nuevo',
        'motivo',
        'registrado_por',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'estado_anterior' => EstadoItemInventario::class,
            'estado_nuevo' => EstadoItemInventario::class,
        ];
    }

    /** @return BelongsTo<ItemInventario, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemInventario::class, 'item_inventario_id');
    }

    /** @return BelongsTo<User, $this> */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    /** @param Builder<$this> $consulta */
    public function scopeMasRecientesPrimero(Builder $consulta): void
    {
        $consulta->orderByDesc('created_at')->orderByDesc('id');
    }

    /** Unidades que entran al inventario. */
    public function esEntrada(): bool
    {
        return $this->estado_anterior === null;
    }

    /** Unidades que salen del inventario, por avería o por cualquier otra causa. */
    public function esSalida(): bool
    {
        return $this->estado_nuevo === EstadoItemInventario::DadoDeBaja;
    }
}
