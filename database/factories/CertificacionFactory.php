<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Certificacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Certificacion>
 */
class CertificacionFactory extends Factory
{
    protected $model = Certificacion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->randomElement([
                'Acreditación en simulación clínica',
                'Centro de entrenamiento certificado',
            ]),
            'entidad' => $this->faker->randomElement(['SSH', 'American Heart Association', 'Ministerio de Salud']),
            'imagen_insignia' => null,
            'descripcion' => $this->faker->sentence(),
            'orden' => $this->faker->numberBetween(0, 10),
            'activo' => true,
        ];
    }
}
