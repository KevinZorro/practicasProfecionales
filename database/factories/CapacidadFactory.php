<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Capacidad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Capacidad>
 */
class CapacidadFactory extends Factory
{
    protected $model = Capacidad::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->unique()->randomElement([
                'Sangrado',
                'Llanto',
                'Signos vitales',
                'Convulsión',
                'Auscultación',
                'Vía aérea',
                'Pulso palpable',
            ]),
            'icono' => 'heroicon-o-heart',
        ];
    }
}
