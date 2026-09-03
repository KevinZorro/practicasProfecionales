<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GaleriaFoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GaleriaFoto>
 */
class GaleriaFotoFactory extends Factory
{
    protected $model = GaleriaFoto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'titulo' => $this->faker->sentence(3),
            'imagen_path' => 'landing/galeria/'.$this->faker->uuid().'.jpg',
            'orden' => $this->faker->numberBetween(0, 20),
            'activo' => true,
        ];
    }
}
