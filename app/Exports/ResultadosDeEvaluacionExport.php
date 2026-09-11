<?php

declare(strict_types=1);

namespace App\Exports;

use App\Enums\Reporte;
use App\Models\EvaluacionEstudiante;

/** RF55. */
final class ResultadosDeEvaluacionExport extends ReporteExport
{
    public function reporte(): Reporte
    {
        return Reporte::ResultadosDeEvaluacion;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['Fecha', 'Materia', 'Semestre', 'Docente', 'Estudiante', 'Tipo de evaluación', 'Intento', 'Resultado'];
    }

    /**
     * @param  EvaluacionEstudiante  $fila
     * @return list<string|int>
     */
    public function map($fila): array
    {
        $solicitud = $fila->evaluacion->solicitud;

        return [
            $solicitud->fecha->format('Y-m-d'),
            (string) $solicitud->materia->nombre,
            (int) $solicitud->materia->semestre,
            (string) $fila->evaluacion->docente->nombre,
            (string) $fila->estudiante->nombre,
            (string) $fila->evaluacion->tipoEvaluacion->nombre,
            $fila->intento,
            $fila->resultado?->etiqueta() ?? 'Sin registrar',
        ];
    }
}
