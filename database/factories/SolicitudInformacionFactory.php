<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SolicitudInformacion;
use App\Models\Taller;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SolicitudInformacion>
 */
class SolicitudInformacionFactory extends Factory
{
    protected $model = SolicitudInformacion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'taller_id' => Taller::factory(),
            'nombre' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'telefono' => $this->faker->numerify('3#########'),
            'mensaje' => $this->faker->optional()->sentence(),
            'enviado_at' => now(),
        ];
    }
}
