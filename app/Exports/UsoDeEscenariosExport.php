<?php

declare(strict_types=1);

namespace App\Exports;

use App\Enums\Reporte;
use App\Models\Solicitud;

/** RF54. */
final class UsoDeEscenariosExport extends ReporteExport
{
    public function reporte(): Reporte
    {
        return Reporte::UsoDeEscenarios;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['Docente', 'Materia', 'Semestre', 'Caso clínico', 'Sala', 'Tipo de sesión', 'Sesiones', 'Horas', 'Estudiantes'];
    }

    /**
     * @param  Solicitud  $fila
     * @return list<string|int|float>
     */
    public function map($fila): array
    {
        return [
            (string) $fila->docente_nombre,
            (string) $fila->materia_nombre,
            (int) $fila->materia_semestre,
            (string) $fila->caso_clinico_nombre,
            (string) $fila->sala_nombre,
            $fila->tipo->etiqueta(),
            (int) $fila->sesiones,
            round((float) $fila->horas, 2),
            (int) $fila->estudiantes,
        ];
    }
}
