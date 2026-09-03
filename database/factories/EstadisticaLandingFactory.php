<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EstadisticaLanding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EstadisticaLanding>
 */
class EstadisticaLandingFactory extends Factory
{
    protected $model = EstadisticaLanding::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'etiqueta' => $this->faker->randomElement(['Salas de simulación', 'Simuladores', 'Estudiantes por semestre', 'Casos clínicos']),
            'valor' => (string) $this->faker->numberBetween(5, 2000),
            'orden' => $this->faker->numberBetween(0, 10),
            'activo' => true,
        ];
    }
}
