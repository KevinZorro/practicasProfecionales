<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PlantillaConfidencialidad;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlantillaConfidencialidad>
 */
class PlantillaConfidencialidadFactory extends Factory
{
    protected $model = PlantillaConfidencialidad::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Formato de confidencialidad y autorización de captación de imágenes',
            'archivo_path' => 'confidencialidad/plantillas/'.$this->faker->uuid().'.pdf',
            'version' => $this->faker->numerify('#.#'),
            'activo' => true,
            'subido_por' => User::factory(),
        ];
    }

    public function inactiva(): static
    {
        return $this->state(fn (array $atributos): array => ['activo' => false]);
    }
}
