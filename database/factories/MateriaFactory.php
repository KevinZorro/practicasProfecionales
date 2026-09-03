<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Materia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Materia>
 */
class MateriaFactory extends Factory
{
    protected $model = Materia::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => strtoupper($this->faker->unique()->bothify('???-###')),
            'nombre' => $this->faker->randomElement([
                'Cuidado Materno Perinatal',
                'Urgencias y Trauma',
                'Enfermería del Adulto',
                'Cuidado Crítico Neonatal',
                'Semiología',
                'Farmacología Aplicada',
            ]),
            'semestre' => $this->faker->numberBetween(1, 10),
            'activo' => true,
        ];
    }

    public function inactiva(): static
    {
        return $this->state(fn (array $atributos): array => ['activo' => false]);
    }
}
