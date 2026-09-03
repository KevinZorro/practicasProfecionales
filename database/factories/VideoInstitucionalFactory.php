<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\VideoInstitucional;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VideoInstitucional>
 */
class VideoInstitucionalFactory extends Factory
{
    protected $model = VideoInstitucional::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'titulo' => $this->faker->sentence(4),
            'url' => 'https://www.youtube.com/watch?v='.$this->faker->lexify('???????????'),
            'orden' => $this->faker->numberBetween(0, 10),
            'activo' => true,
        ];
    }
}
