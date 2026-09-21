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
     * Mantiene la invariante del ítem pase lo que pase con los overrides.
     *
     * Un test que pide create(['cantidad_total' => 6]) no tiene por qué
     * acordarse de repartir las seis unidades entre los contadores: aquí
     * quedan todas operativas, y los estados de abajo mueven las que hagan
     * falta sobre ese total ya fijado. Sin esto, el CHECK de la base rechaza
     * la fila con un error de PostgreSQL en vez de con algo legible.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (ItemInventario $item): void {
            $item->cantidad_operativa = $item->cantidad_total;
            $item->cantidad_en_revision = 0;
            $item->cantidad_defectuosa = 0;
        });
    }

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
            'cantidad_total' => $cantidad = $this->faker->numberBetween(1, 30),
            // Por defecto todas operativas: la invariante del ítem es
            // total = operativa + en revisión + defectuosa, y el CHECK de la
            // base no deja crear una fila que no cuadre.
            'cantidad_operativa' => $cantidad,
            'cantidad_en_revision' => 0,
            'cantidad_defectuosa' => 0,
            'descripcion' => $this->faker->sentence(),
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
            'cantidad_total' => $cantidad = $this->faker->numberBetween(1, 4),
            'cantidad_operativa' => $cantidad,
        ]);
    }

    public function equipoBasico(): static
    {
        return $this->state(fn (array $atributos): array => [
            'nombre' => $this->faker->randomElement(['Guantes de nitrilo', 'Gasas estériles', 'Jeringas 10 ml', 'Batas desechables']),
            'tipo' => TipoItemInventario::EquipoBasico,
            'nivel_fidelidad' => null,
            'cantidad_total' => $cantidad = $this->faker->numberBetween(50, 500),
            'cantidad_operativa' => $cantidad,
        ]);
    }

    /**
     * Deja N unidades en el estado dado, quitándolas de las operativas. El
     * total no cambia: siguen estando en el inventario.
     *
     * Se aplica después de configure(), así que trabaja sobre el total
     * definitivo aunque el test lo haya sobrescrito.
     */
    public function conUnidadesEn(EstadoItemInventario $estado, ?int $cantidad = null): static
    {
        return $this->afterMaking(function (ItemInventario $item) use ($estado, $cantidad): void {
            $mueve = min($cantidad ?? $item->cantidad_operativa, $item->cantidad_operativa);

            $item->cantidad_operativa -= $mueve;
            $item->{$estado->columnaDeCantidad()} += $mueve;
        });
    }

    /** Todas las unidades en revisión. */
    public function enRevision(?int $cantidad = null): static
    {
        return $this->conUnidadesEn(EstadoItemInventario::EnRevision, $cantidad);
    }

    /** Todas las unidades defectuosas, salvo que se pidan menos. */
    public function defectuoso(?int $cantidad = null): static
    {
        return $this->conUnidadesEn(EstadoItemInventario::Defectuoso, $cantidad);
    }

    /** Sin unidades: todo lo que tenía se dio de baja. */
    public function dadoDeBaja(): static
    {
        return $this->afterMaking(function (ItemInventario $item): void {
            $item->cantidad_total = 0;
            $item->cantidad_operativa = 0;
            $item->cantidad_en_revision = 0;
            $item->cantidad_defectuosa = 0;
            $item->activo = false;
        });
    }
}
