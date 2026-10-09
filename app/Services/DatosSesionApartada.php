<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TipoSesion;

/**
 * Lo que trae el formato físico de una sesión apartada antes del semestre
 * (RF57). No incluye insumos: el formato intramural llega después (RF59).
 * Tampoco estudiantes: los pone el docente antes de la sesión (D15).
 */
final readonly class DatosSesionApartada
{
    /**
     * @param  string  $fecha  en formato Y-m-d
     * @param  string  $horaInicio  en formato H:i
     * @param  string  $horaFin  en formato H:i
     */
    public function __construct(
        public int $docenteId,
        public int $materiaId,
        public int $casoClinicoId,
        public TipoSesion $tipo,
        public string $fecha,
        public string $horaInicio,
        public string $horaFin,
        public int $cantidadEstudiantes,
        public ?string $grupo = null,
        public ?string $observaciones = null,
    ) {}
}
