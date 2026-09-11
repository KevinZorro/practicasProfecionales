<?php

declare(strict_types=1);

namespace App\Exports;

use App\Enums\Reporte;
use App\Models\Solicitud;

/** RF56. */
final class EvaluacionesNoRegistradasExport extends ReporteExport
{
    public function reporte(): Reporte
    {
        return Reporte::EvaluacionesNoRegistradas;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['Fecha', 'Docente', 'Materia', 'Semestre', 'Caso clínico', 'Estudiantes', 'Motivo'];
    }

    /**
     * @param  Solicitud  $fila
     * @return list<string|int>
     */
    public function map($fila): array
    {
        return [
            $fila->fecha->format('Y-m-d'),
            (string) $fila->docente->nombre,
            (string) $fila->materia->nombre,
            (int) $fila->materia->semestre,
            (string) $fila->casoClinico->nombre,
            $fila->cantidad_estudiantes,
            $fila->evaluacion === null ? 'Sin registrar' : 'En borrador, sin finalizar',
        ];
    }
}
