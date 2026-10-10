<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EquipoDestacado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipoDestacado>
 */
class EquipoDestacadoFactory extends Factory
{
    protected $model = EquipoDestacado::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->randomElement([
                'Simulador de alta fidelidad',
                'Sala inmersiva',
                'Mesa de anatomía virtual',
            ]),
            'resumen' => $this->faker->sentence(),
            'imagen' => null,
            'caracteristicas' => ['Signos vitales en tiempo real', 'Respuesta a cada maniobra'],
            'orden' => $this->faker->numberBetween(0, 10),
            'activo' => true,
        ];
    }
}
