<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstadoUsuario;
use App\Enums\OrigenUsuario;
use App\Enums\Rol;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'google_id' => (string) $this->faker->unique()->numerify('##################'),
            'nombre' => $this->faker->name(),
            'email' => $this->faker->unique()->userName().'@ejemplo.edu.co',
            'documento' => (string) $this->faker->unique()->numerify('##########'),
            'codigo_institucional' => (string) $this->faker->unique()->numerify('########'),
            'estado' => EstadoUsuario::Activo,
            'origen' => OrigenUsuario::Contratado,
            'ultima_sincronizacion' => now(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $atributos): array => [
            'estado' => EstadoUsuario::Inactivo,
        ]);
    }

    public function docente(): static
    {
        return $this->afterCreating(fn (User $usuario) => $usuario->assignRole(Rol::Docente->value));
    }

    public function estudiante(): static
    {
        return $this->state(fn (array $atributos): array => [
            'origen' => OrigenUsuario::Matriculado,
        ])->afterCreating(fn (User $usuario) => $usuario->assignRole(Rol::Estudiante->value));
    }

    public function coordinador(): static
    {
        return $this->afterCreating(fn (User $usuario) => $usuario->assignRole(Rol::Coordinador->value));
    }

    public function administrativo(): static
    {
        return $this->afterCreating(fn (User $usuario) => $usuario->assignRole(Rol::Administrativo->value));
    }

    public function admin(): static
    {
        return $this->afterCreating(fn (User $usuario) => $usuario->assignRole(Rol::Admin->value));
    }

    public function sinVerificar(): static
    {
        return $this->state(fn (array $atributos): array => [
            'email_verified_at' => null,
        ]);
    }
}
