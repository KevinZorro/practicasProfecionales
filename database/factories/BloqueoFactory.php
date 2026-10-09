<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Bloqueo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bloqueo>
 */
class BloqueoFactory extends Factory
{
    protected $model = Bloqueo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->estudiante(),
            'motivo' => 'Incumplimiento del reglamento del laboratorio.',
            'bloqueado_por' => User::factory()->coordinador(),
        ];
    }

    public function levantado(): static
    {
        return $this->state(fn (array $atributos): array => [
            'levantado_at' => now(),
            'levantado_por' => User::factory()->coordinador(),
            'motivo_levantamiento' => 'Cumplió la sanción.',
        ]);
    }
}
