<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ListaDeReposicion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ListaDeReposicion>
 */
class ListaDeReposicionFactory extends Factory
{
    protected $model = ListaDeReposicion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'desde' => '2026-07-01',
            'hasta' => '2026-12-15',
            'observaciones' => null,
            'cerrada_por' => null,
            'cerrada_at' => null,
        ];
    }

    public function cerrada(): static
    {
        return $this->state(fn (array $atributos): array => [
            'cerrada_por' => User::factory()->coordinador(),
            'cerrada_at' => now(),
        ]);
    }
}
