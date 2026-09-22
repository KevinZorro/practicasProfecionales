<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Rol;
use App\Models\AsignacionDeRol;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<AsignacionDeRol>
 */
class AsignacionDeRolFactory extends Factory
{
    protected $model = AsignacionDeRol::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'role_id' => fn (): int => Role::findByName(Rol::Administrativo->value)->id,
            'desde' => now()->toDateString(),
            'hasta' => null,
            'motivo' => null,
            'asignado_por' => User::factory()->admin(),
            'revocada_at' => null,
            'revocada_por' => null,
        ];
    }

    public function temporal(string $hasta): static
    {
        return $this->state(fn (array $atributos): array => ['hasta' => $hasta]);
    }

    public function revocada(): static
    {
        return $this->state(fn (array $atributos): array => [
            'revocada_at' => now(),
            'revocada_por' => User::factory()->admin(),
        ]);
    }
}
