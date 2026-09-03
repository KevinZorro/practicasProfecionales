<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstadoSolicitud;
use App\Enums\TipoSesion;
use App\Models\CasoClinico;
use App\Models\Materia;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Solicitud>
 */
class SolicitudFactory extends Factory
{
    protected $model = Solicitud::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $horaInicio = $this->faker->numberBetween(7, 16);

        return [
            'docente_id' => User::factory(),
            'materia_id' => Materia::factory(),
            'caso_clinico_id' => CasoClinico::factory(),
            'tipo' => TipoSesion::Practica,
            'fecha' => $this->faker->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
            'hora_inicio' => sprintf('%02d:00:00', $horaInicio),
            'hora_fin' => sprintf('%02d:00:00', $horaInicio + 2),
            'cantidad_estudiantes' => $this->faker->numberBetween(4, 25),
            'estado' => EstadoSolicitud::Pendiente,
            'observaciones' => $this->faker->optional()->sentence(),
        ];
    }

    public function deEvaluacion(): static
    {
        return $this->state(fn (array $atributos): array => [
            'tipo' => TipoSesion::Evaluacion,
        ]);
    }

    public function revisada(): static
    {
        return $this->state(fn (array $atributos): array => [
            'estado' => EstadoSolicitud::Revisada,
            'revisada_por' => User::factory(),
            'revisada_at' => now(),
        ]);
    }

    public function aprobada(): static
    {
        return $this->state(fn (array $atributos): array => [
            'estado' => EstadoSolicitud::Aprobada,
            'revisada_por' => User::factory(),
            'revisada_at' => now()->subDay(),
            'resuelta_por' => User::factory(),
            'resuelta_at' => now(),
        ]);
    }

    public function rechazada(): static
    {
        return $this->state(fn (array $atributos): array => [
            'estado' => EstadoSolicitud::Rechazada,
            'revisada_por' => User::factory(),
            'revisada_at' => now()->subDay(),
            'resuelta_por' => User::factory(),
            'resuelta_at' => now(),
            'motivo_rechazo' => 'No hay simulador de alta fidelidad disponible en esa franja.',
        ]);
    }
}
