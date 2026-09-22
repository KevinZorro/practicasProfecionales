<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MotivoReposicion;
use App\Models\LineaDeReposicion;
use App\Models\ListaDeReposicion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LineaDeReposicion>
 */
class LineaDeReposicionFactory extends Factory
{
    protected $model = LineaDeReposicion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lista_reposicion_id' => ListaDeReposicion::factory()->cerrada(),
            'item_inventario_id' => null,
            'descripcion' => 'Gasas estériles',
            'motivo' => MotivoReposicion::Consumo,
            'cantidad' => $this->faker->numberBetween(1, 50),
        ];
    }
}
