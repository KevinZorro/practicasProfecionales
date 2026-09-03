<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstadoConsentimiento;
use App\Models\ConsentimientoEstudiante;
use App\Models\ConsentimientoPlantilla;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsentimientoEstudiante>
 */
class ConsentimientoEstudianteFactory extends Factory
{
    protected $model = ConsentimientoEstudiante::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'estudiante_id' => User::factory(),
            'plantilla_id' => ConsentimientoPlantilla::factory(),
            'periodo_academico' => '2026-2',
            'archivo_firmado_path' => null,
            'estado' => EstadoConsentimiento::Pendiente,
            'verificado_por' => null,
            'verificado_at' => null,
        ];
    }

    public function cargado(): static
    {
        return $this->state(fn (array $atributos): array => [
            'archivo_firmado_path' => 'consentimientos/firmados/'.$this->faker->uuid().'.pdf',
            'estado' => EstadoConsentimiento::Cargado,
        ]);
    }

    public function verificado(): static
    {
        return $this->state(fn (array $atributos): array => [
            'archivo_firmado_path' => 'consentimientos/firmados/'.$this->faker->uuid().'.pdf',
            'estado' => EstadoConsentimiento::Verificado,
            'verificado_por' => User::factory(),
            'verificado_at' => now(),
        ]);
    }

    public function delPeriodo(string $periodo): static
    {
        return $this->state(fn (array $atributos): array => [
            'periodo_academico' => $periodo,
        ]);
    }
}
