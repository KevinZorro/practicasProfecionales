<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Evaluacion;
use App\Models\EvaluacionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluacionItem>
 */
class EvaluacionItemFactory extends Factory
{
    protected $model = EvaluacionItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'evaluacion_id' => Evaluacion::factory(),
            'descripcion' => $this->faker->sentence(),
            'orden' => $this->faker->numberBetween(1, 15),
        ];
    }
}
