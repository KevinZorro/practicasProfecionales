<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstadoItemInventario;
use App\Models\CambioEstadoItem;
use App\Models\ItemInventario;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CambioEstadoItem>
 */
class CambioEstadoItemFactory extends Factory
{
    protected $model = CambioEstadoItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_inventario_id' => ItemInventario::factory(),
            'estado_anterior' => EstadoItemInventario::Operativo,
            'estado_nuevo' => EstadoItemInventario::EnRevision,
            'motivo' => 'El balón de la sonda no infla.',
            'registrado_por' => User::factory()->administrativo(),
        ];
    }
}
