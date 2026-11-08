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
            // Cabe cualquier grupo de SolicitudFactory (hasta 25): el tablero
            // no ofrece salas en las que no cabe el grupo. Quien necesite una
            // sala chica la fija.
            'capacidad' => $this->faker->numberBetween(25, 40),
            'activo' => true,
            'bloque' => 'A',
            'piso' => '1',
            'numero' => (string) $this->faker->unique()->numberBetween(1, 999),
        ];
    }

    public function inactiva(): static
    {
        return $this->state(fn (array $atributos): array => ['activo' => false]);
    }
}
