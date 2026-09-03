<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ItemChecklist;
use App\Models\TipoEvaluacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemChecklist>
 */
class ItemChecklistFactory extends Factory
{
    protected $model = ItemChecklist::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo_evaluacion_id' => TipoEvaluacion::factory(),
            'descripcion' => $this->faker->randomElement([
                'Verifica la identidad del paciente',
                'Realiza higiene de manos antes del procedimiento',
                'Selecciona el calibre adecuado del catéter',
                'Aplica técnica aséptica durante todo el procedimiento',
                'Registra el procedimiento en la historia clínica',
                'Descarta el material cortopunzante en el guardián',
            ]),
            'orden' => $this->faker->numberBetween(1, 15),
        ];
    }
}
