<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CasoClinico;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CasoClinico>
 */
class CasoClinicoFactory extends Factory
{
    protected $model = CasoClinico::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->randomElement([
                'Atención de parto normal',
                'Herida por arma de fuego',
                'Reanimación neonatal en incubadora',
                'Shock hipovolémico',
                'Crisis convulsiva',
                'Paro cardiorrespiratorio en adulto',
            ]),
            'descripcion' => $this->faker->paragraph(),
            // Sin definir por defecto: es un dato que el ADMIN registra, y
            // así un test que no hable de capacidad no queda limitado por
            // un número que nadie eligió.
            'capacidad_maxima_estudiantes' => null,
            'imagen' => null,
            'visible_publico' => $this->faker->boolean(70),
            'orden' => $this->faker->numberBetween(0, 20),
            'activo' => true,
        ];
    }

    public function conCapacidad(int $maximo): static
    {
        return $this->state(fn (array $atributos): array => [
            'capacidad_maxima_estudiantes' => $maximo,
        ]);
    }

    public function visibleEnPublico(): static
    {
        return $this->state(fn (array $atributos): array => [
            'visible_publico' => true,
            'activo' => true,
        ]);
    }
}
