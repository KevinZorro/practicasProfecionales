<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ConfiguracionLanding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConfiguracionLanding>
 */
class ConfiguracionLandingFactory extends Factory
{
    protected $model = ConfiguracionLanding::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clave' => $this->faker->unique()->slug(2),
            'valor' => $this->faker->sentence(),
        ];
    }
}
