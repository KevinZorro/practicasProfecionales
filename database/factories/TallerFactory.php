<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ModalidadTaller;
use App\Models\Taller;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Taller>
 */
class TallerFactory extends Factory
{
    protected $model = Taller::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'titulo' => $this->faker->randomElement([
                'Taller de reanimación cardiopulmonar',
                'Taller de atención inicial del trauma',
                'Taller de lactancia y cuidado neonatal',
            ]),
            'descripcion' => $this->faker->paragraph(),
            'imagen' => null,
            'tema' => $this->faker->randomElement(['Urgencias', 'Materno perinatal', 'Cuidado crítico']),
            'fecha' => $this->faker->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
            'modalidad' => $this->faker->randomElement(ModalidadTaller::cases()),
            'muestra_formulario' => true,
            'orden' => $this->faker->numberBetween(0, 10),
            'activo' => true,
        ];
    }
}
