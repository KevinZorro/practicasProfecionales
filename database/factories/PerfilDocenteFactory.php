<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PerfilDocente;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerfilDocente>
 */
class PerfilDocenteFactory extends Factory
{
    protected $model = PerfilDocente::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'nombre' => $this->faker->name(),
            'cargo' => $this->faker->randomElement([
                'Coordinadora del laboratorio',
                'Docente de simulación',
                'Instructor de urgencias',
            ]),
            'foto' => null,
            'orden' => $this->faker->numberBetween(0, 10),
            'activo' => true,
        ];
    }

    public function conUsuario(?User $usuario = null): static
    {
        return $this->state(fn (array $atributos): array => [
            'user_id' => $usuario?->id ?? User::factory(),
        ]);
    }
}
