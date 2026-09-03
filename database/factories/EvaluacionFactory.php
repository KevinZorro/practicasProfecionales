<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstadoEvaluacion;
use App\Models\Evaluacion;
use App\Models\Solicitud;
use App\Models\TipoEvaluacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evaluacion>
 */
class EvaluacionFactory extends Factory
{
    protected $model = Evaluacion::class;

    /**
     * La solicitud asociada nace de tipo evaluación y aprobada: ninguna
     * evaluación existe sin escenario apartado.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'solicitud_id' => Solicitud::factory()->deEvaluacion()->aprobada(),
            'tipo_evaluacion_id' => TipoEvaluacion::factory(),
            'docente_id' => User::factory(),
            'estado' => EstadoEvaluacion::Borrador,
        ];
    }

    public function finalizada(): static
    {
        return $this->state(fn (array $atributos): array => [
            'estado' => EstadoEvaluacion::Finalizada,
        ]);
    }
}
