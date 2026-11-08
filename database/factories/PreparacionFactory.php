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
     * Sin sala: la asigna el administrativo el día de la práctica (P16: de
     * una a tres, en preparacion_sala).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'solicitud_id' => Solicitud::factory()->aprobada(),
            'estado' => EstadoPreparacion::Pendiente,
            'preparado_por' => null,
            'preparado_at' => null,
            'observaciones' => null,
        ];
    }

    /** Con estas salas, o con una nueva si no se dice cuál. */
    public function conSala(Sala ...$salas): static
    {
        return $this->afterCreating(static function (Preparacion $preparacion) use ($salas): void {
            $preparacion->salas()->attach($salas === [] ? [Sala::factory()->create()->id] : array_map(static fn (Sala $sala): int => $sala->id, $salas));
        });
    }

    public function preparada(): static
    {
        return $this->conSala()->state(fn (array $atributos): array => [
            'estado' => EstadoPreparacion::Preparado,
            'preparado_por' => User::factory(),
            'preparado_at' => now(),
        ]);
    }
}
