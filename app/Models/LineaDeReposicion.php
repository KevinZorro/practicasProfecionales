<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MotivoReposicion;
use Database\Factories\LineaDeReposicionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una línea congelada de una lista de reposición cerrada (RF67).
 *
 * La descripción se guarda copiada y no se lee del ítem: si mañana renombran
 * el ítem, la carta que ya se entregó tiene que seguir diciendo lo que decía.
 * El item_inventario_id se conserva igual, para poder rastrear de dónde vino.
 */
class LineaDeReposicion extends Model
{
    /** @use HasFactory<LineaDeReposicionFactory> */
    use HasFactory;

    protected $table = 'lineas_reposicion';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'lista_reposicion_id',
        'item_inventario_id',
        'descripcion',
        'motivo',
        'cantidad',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'motivo' => MotivoReposicion::class,
            'cantidad' => 'integer',
        ];
    }

    /** @return BelongsTo<ListaDeReposicion, $this> */
    public function lista(): BelongsTo
    {
        return $this->belongsTo(ListaDeReposicion::class, 'lista_reposicion_id');
    }

    /** @return BelongsTo<ItemInventario, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemInventario::class, 'item_inventario_id');
    }
}
