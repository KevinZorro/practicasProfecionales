<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Sala;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sala>
 */
class SalaFactory extends Factory
{
    protected $model = Sala::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Sala de simulación '.$this->faker->unique()->numberBetween(1, 99),
            'codigo' => strtoupper($this->faker->unique()->bothify('SIM-##')),
            'capacidad' => $this->faker->numberBetween(6, 30),
            'activo' => true,
        ];
    }

    public function inactiva(): static
    {
        return $this->state(fn (array $atributos): array => ['activo' => false]);
    }
}
