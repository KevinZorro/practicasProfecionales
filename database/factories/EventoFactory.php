<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Evento;
use App\Models\TipoEvento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evento>
 */
class EventoFactory extends Factory
{
    protected $model = Evento::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'titulo' => $this->faker->randomElement([
                'Jornada de simulación clínica',
                'Congreso de enfermería',
                'Seminario de seguridad del paciente',
            ]),
            'descripcion' => $this->faker->paragraph(),
            'imagen' => null,
            'fecha' => $this->faker->dateTimeBetween('now', '+6 months')->format('Y-m-d'),
            'tipo_evento_id' => TipoEvento::factory(),
            'abierto_publico' => true,
            'orden' => $this->faker->numberBetween(0, 10),
            'activo' => true,
        ];
    }
}
