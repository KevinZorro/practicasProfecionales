<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NecesidadDeReposicion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NecesidadDeReposicion>
 */
class NecesidadDeReposicionFactory extends Factory
{
    protected $model = NecesidadDeReposicion::class;

    /**
     * Por defecto, lo que no está en el catálogo: es el caso que motivó el
     * requerimiento.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_inventario_id' => null,
            'descripcion' => 'Pila CR2032 para el control del desfibrilador',
            'cantidad' => 2,
            'justificacion' => 'Se pidió para la práctica y no había.',
            'fecha' => '2026-09-15',
            'registrada_por' => User::factory()->administrativo(),
        ];
    }

    /** Hace falta más de algo que ya está en el inventario. */
    public function deUnItem(int $itemId, ?string $descripcion = null): static
    {
        return $this->state(fn (array $atributos): array => [
            'item_inventario_id' => $itemId,
            'descripcion' => $descripcion,
        ]);
    }

    public function enLaFecha(string $fecha): static
    {
        return $this->state(fn (array $atributos): array => [
            'fecha' => $fecha,
        ]);
    }
}
