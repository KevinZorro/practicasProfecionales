<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstadoFormatoConfidencialidad;
use App\Models\FormatoConfidencialidad;
use App\Models\PlantillaConfidencialidad;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormatoConfidencialidad>
 */
class FormatoConfidencialidadFactory extends Factory
{
    protected $model = FormatoConfidencialidad::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'firmante_id' => User::factory(),
            'plantilla_id' => PlantillaConfidencialidad::factory(),
            'periodo_academico' => '2026-2',
            'archivo_firmado_path' => null,
            'recibido_fisico_at' => null,
            'recibido_fisico_por' => null,
            'estado' => EstadoFormatoConfidencialidad::Pendiente,
            'verificado_por' => null,
            'verificado_at' => null,
        ];
    }

    public function cargado(): static
    {
        return $this->state(fn (array $atributos): array => [
            'archivo_firmado_path' => 'confidencialidad/firmados/'.$this->faker->uuid().'.pdf',
            'estado' => EstadoFormatoConfidencialidad::Cargado,
        ]);
    }

    public function verificado(): static
    {
        return $this->state(fn (array $atributos): array => [
            'archivo_firmado_path' => 'confidencialidad/firmados/'.$this->faker->uuid().'.pdf',
            'estado' => EstadoFormatoConfidencialidad::Verificado,
            'verificado_por' => User::factory(),
            'verificado_at' => now(),
        ]);
    }

    /**
     * Entregado en físico en la puerta (RF53). Es un estado aparte del
     * documento escaneado, así que se combina con los demás:
     * ->entregadoEnFisico()->cargado() es un caso real.
     */
    public function entregadoEnFisico(): static
    {
        return $this->state(fn (array $atributos): array => [
            'recibido_fisico_at' => now()->subDays(2),
            'recibido_fisico_por' => User::factory()->administrativo(),
        ]);
    }

    public function delPeriodo(string $periodo): static
    {
        return $this->state(fn (array $atributos): array => [
            'periodo_academico' => $periodo,
        ]);
    }
}
