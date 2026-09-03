<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PerfilDocente;
use App\Models\TituloDocente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TituloDocente>
 */
class TituloDocenteFactory extends Factory
{
    protected $model = TituloDocente::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'perfil_docente_id' => PerfilDocente::factory(),
            'titulo' => $this->faker->randomElement([
                'Enfermera profesional',
                'Especialista en cuidado crítico',
                'Magíster en educación para la salud',
            ]),
            'institucion' => $this->faker->randomElement(['Universidad Nacional', 'Universidad de Antioquia', 'Universidad del Valle']),
            'orden' => $this->faker->numberBetween(0, 5),
        ];
    }
}
