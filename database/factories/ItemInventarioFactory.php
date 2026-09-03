<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstadoItemInventario;
use App\Enums\NivelFidelidad;
use App\Enums\TipoItemInventario;
use App\Models\ItemInventario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemInventario>
 */
class ItemInventarioFactory extends Factory
{
    protected $model = ItemInventario::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->randomElement([
                'Tensiómetro',
                'Fonendoscopio',
                'Set de curación',
                'Bomba de infusión',
                'Camilla de traslado',
                'Monitor de signos vitales',
            ]),
            'tipo' => TipoItemInventario::EquipoClinico,
            'nivel_fidelidad' => null,
            'cantidad_total' => $this->faker->numberBetween(1, 30),
            'descripcion' => $this->faker->sentence(),
            'estado' => EstadoItemInventario::Disponible,
            'activo' => true,
        ];
    }

    /**
     * El nivel de fidelidad solo aplica a simuladores.
     */
    public function simulador(?NivelFidelidad $nivel = null): static
    {
        return $this->state(fn (array $atributos): array => [
            'nombre' => $this->faker->randomElement([
                'Maniquí de parto',
                'Simulador neonatal',
                'Simulador de trauma adulto',
                'Torso de RCP',
            ]),
            'tipo' => TipoItemInventario::Simulador,
            'nivel_fidelidad' => $nivel ?? $this->faker->randomElement(NivelFidelidad::cases()),
            'cantidad_total' => $this->faker->numberBetween(1, 4),
        ]);
    }

    public function equipoBasico(): static
    {
        return $this->state(fn (array $atributos): array => [
            'nombre' => $this->faker->randomElement(['Guantes de nitrilo', 'Gasas estériles', 'Jeringas 10 ml', 'Batas desechables']),
            'tipo' => TipoItemInventario::EquipoBasico,
            'nivel_fidelidad' => null,
            'cantidad_total' => $this->faker->numberBetween(50, 500),
        ]);
    }

    public function enMantenimiento(): static
    {
        return $this->state(fn (array $atributos): array => [
            'estado' => EstadoItemInventario::Mantenimiento,
        ]);
    }
}
