<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstadoPreparacion;
use App\Models\Preparacion;
use App\Models\Sala;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Preparacion>
 */
class PreparacionFactory extends Factory
{
    protected $model = Preparacion::class;

    /**
     * La sala llega nula: la asigna el administrativo el día de la práctica.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'solicitud_id' => Solicitud::factory()->aprobada(),
            'sala_id' => null,
            'estado' => EstadoPreparacion::Pendiente,
            'preparado_por' => null,
            'preparado_at' => null,
            'observaciones' => null,
        ];
    }

    public function conSala(?Sala $sala = null): static
    {
        return $this->state(fn (array $atributos): array => [
            'sala_id' => $sala?->id ?? Sala::factory(),
        ]);
    }

    public function preparada(): static
    {
        return $this->state(fn (array $atributos): array => [
            'sala_id' => Sala::factory(),
            'estado' => EstadoPreparacion::Preparado,
            'preparado_por' => User::factory(),
            'preparado_at' => now(),
        ]);
    }
}
