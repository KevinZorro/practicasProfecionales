<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PeriodoAcademico;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PeriodoAcademico>
 */
class PeriodoAcademicoFactory extends Factory
{
    protected $model = PeriodoAcademico::class;

    /**
     * Abierto por defecto, que es el estado en el que lo necesitan casi
     * todos los tests.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->unique()->numerify('20##-#'),
            'abierto_at' => now(),
            'abierto_por' => User::factory(),
            'cerrado_at' => null,
            'cerrado_por' => null,
        ];
    }

    public function cerrado(): static
    {
        return $this->state(fn (array $atributos): array => [
            'cerrado_at' => now(),
            'cerrado_por' => User::factory(),
        ]);
    }
}
