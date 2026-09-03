<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ConsentimientoPlantilla;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsentimientoPlantilla>
 */
class ConsentimientoPlantillaFactory extends Factory
{
    protected $model = ConsentimientoPlantilla::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Consentimiento informado de prácticas de simulación',
            'archivo_path' => 'consentimientos/plantillas/'.$this->faker->uuid().'.pdf',
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
