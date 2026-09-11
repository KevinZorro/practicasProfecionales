<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Los tres reportes agregados del §7 del documento de arquitectura.
 *
 * Existe para que el nombre de cada reporte no se escriba a mano en rutas,
 * plantillas y clases de exportación.
 */
enum Reporte: string
{
    case UsoDeEscenarios = 'uso_de_escenarios';
    case ResultadosDeEvaluacion = 'resultados_de_evaluacion';
    case EvaluacionesNoRegistradas = 'evaluaciones_no_registradas';

    public function titulo(): string
    {
        return match ($this) {
            self::UsoDeEscenarios => 'Uso de escenarios clínicos',
            self::ResultadosDeEvaluacion => 'Resultados de evaluación',
            self::EvaluacionesNoRegistradas => 'Evaluaciones no registradas',
        };
    }

    /** Nombre del archivo descargado, sin extensión. */
    public function nombreDeArchivo(): string
    {
        return $this->value;
    }

    /** Plantilla Blade que renderiza dompdf. */
    public function plantillaPdf(): string
    {
        return "reportes.pdf.{$this->value}";
    }
}
