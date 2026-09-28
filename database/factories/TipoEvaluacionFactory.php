<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ItemChecklist;
use App\Models\TipoEvaluacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoEvaluacion>
 */
class TipoEvaluacionFactory extends Factory
{
    protected $model = TipoEvaluacion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->randomElement([
                'Canalización de vía periférica',
                'Lavado de manos quirúrgico',
                'Reanimación cardiopulmonar básica',
                'Toma de signos vitales',
                'Sondaje vesical',
            ]),
            'descripcion' => $this->faker->sentence(),
            'activo' => true,
        ];
    }

    /**
     * Con su checklist. No va en la definición por defecto porque varios
     * tests cuentan los ítems que ellos mismos crean.
     */
    public function conChecklist(int $items = 1): static
    {
        return $this->afterCreating(function (TipoEvaluacion $tipo) use ($items): void {
            for ($orden = 1; $orden <= $items; $orden++) {
                ItemChecklist::factory()->create(['tipo_evaluacion_id' => $tipo->id, 'orden' => $orden]);
            }
        });
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $atributos): array => ['activo' => false]);
    }
}
