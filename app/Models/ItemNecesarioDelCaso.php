<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una fila de caso_clinico_item: un ítem del inventario que el caso
 * necesita, con su cantidad (RF25).
 *
 * La relación CasoClinico::items() sigue siendo la que leen los Services
 * para precargar la solicitud. Este modelo existe para que la pantalla del
 * ADMIN pueda editar las filas una a una, con su cantidad: Filament edita
 * bien una relación "tiene muchos" y no tanto los datos de un pivote.
 */
class ItemNecesarioDelCaso extends Model
{
    protected $table = 'caso_clinico_item';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'item_inventario_id',
        'cantidad',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
        ];
    }

    /** @return BelongsTo<CasoClinico, $this> */
    public function casoClinico(): BelongsTo
    {
        return $this->belongsTo(CasoClinico::class);
    }

    /** @return BelongsTo<ItemInventario, $this> */
    public function itemInventario(): BelongsTo
    {
        return $this->belongsTo(ItemInventario::class);
    }
}
