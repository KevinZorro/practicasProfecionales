<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ResultadoEvaluacion;
use App\Models\Evaluacion;
use App\Models\EvaluacionEstudiante;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluacionEstudiante>
 */
class EvaluacionEstudianteFactory extends Factory
{
    protected $model = EvaluacionEstudiante::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'evaluacion_id' => Evaluacion::factory(),
            'estudiante_id' => User::factory(),
            'resultado' => ResultadoEvaluacion::Aprobado,
            'intento' => 1,
            'observaciones' => $this->faker->optional()->sentence(),
        ];
    }

    public function noAprobado(): static
    {
        return $this->state(fn (array $atributos): array => [
            'resultado' => ResultadoEvaluacion::NoAprobado,
        ]);
    }

    public function intento(int $numero): static
    {
        return $this->state(fn (array $atributos): array => [
            'intento' => $numero,
        ]);
    }
}
