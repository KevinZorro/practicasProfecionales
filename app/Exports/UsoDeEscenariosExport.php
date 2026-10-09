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
     * La fila es una agregación (ReporteService::usoDeEscenarios), no una
     * solicitud: sus columnas son alias del SELECT y se leen con
     * getAttribute(), que es lo que son.
     *
     * @param  Solicitud  $fila
     * @return list<string|int|float>
     */
    public function map($fila): array
    {
        return [
            (string) $fila->getAttribute('docente_nombre'),
            (string) $fila->getAttribute('materia_nombre'),
            (int) $fila->getAttribute('materia_semestre'),
            (string) $fila->getAttribute('caso_clinico_nombre'),
            (string) $fila->getAttribute('sala_nombre'),
            $fila->tipo->etiqueta(),
            (int) $fila->getAttribute('sesiones'),
            round((float) $fila->getAttribute('horas'), 2),
            (int) $fila->getAttribute('total_estudiantes'),
        ];
    }
}
