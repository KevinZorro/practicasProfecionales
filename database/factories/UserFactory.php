<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstadoUsuario;
use App\Enums\OrigenUsuario;
use App\Enums\Rol;
use App\Models\FormatoConfidencialidad;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
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
            'remember_token' => Str::random(10),
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $atributos): array => [
            'estado' => EstadoUsuario::Inactivo,
        ]);
    }

    /** Creada por el ADMIN en la plataforma (RF22): la sincronización no la toca. */
    public function manual(): static
    {
        return $this->state(fn (array $atributos): array => [
            'origen' => OrigenUsuario::Manual,
            'ultima_sincronizacion' => null,
        ]);
    }

    /** Deshabilitada por el ADMIN (RF22), aparte de la vigencia institucional. */
    public function deshabilitado(): static
    {
        return $this->state(fn (array $atributos): array => [
            'deshabilitado_at' => now(),
            'motivo_deshabilitacion' => 'Cuenta suspendida por la plataforma.',
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

    /**
     * Puede entrar al laboratorio: formato de confidencialidad verificado del
     * periodo "2026-2" (RF53). El periodo tiene que estar abierto en el test
     * (abrirPeriodo()).
     */
    public function habilitado(): static
    {
        return $this->afterCreating(fn (User $usuario) => FormatoConfidencialidad::factory()->verificado()->create([
            'firmante_id' => $usuario->id,
        ]));
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
