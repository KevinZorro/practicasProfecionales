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
 * Un cambio de estado funcional de un ítem del inventario (RF66).
 *
 * El historial es de solo añadir: cada transición deja una fila con su
 * motivo y su responsable, y no se edita ni se borra. Saber qué pasó con una
 * pieza es el objetivo del requerimiento, no un efecto secundario.
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
}
