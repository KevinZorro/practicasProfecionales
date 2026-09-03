<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TipoEvaluacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoEvaluacion>
 */
class TipoEvaluacionFactory extends Factory
{
    protected $model = TipoEvaluacion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->randomElement([
                'Canalización de vía periférica',
                'Lavado de manos quirúrgico',
                'Reanimación cardiopulmonar básica',
                'Toma de signos vitales',
                'Sondaje vesical',
            ]),
            'descripcion' => $this->faker->sentence(),
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $atributos): array => ['activo' => false]);
    }
}
