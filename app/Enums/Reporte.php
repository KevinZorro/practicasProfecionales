<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Los reportes agregados del §7 del documento de arquitectura, más la lista
 * de reposición del RF67, que usa la misma maquinaria de exportación.
 *
 * Existe para que el nombre de cada reporte no se escriba a mano en rutas,
 * plantillas y clases de exportación.
 */
enum Reporte: string
{
    case UsoDeEscenarios = 'uso_de_escenarios';
    case ResultadosDeEvaluacion = 'resultados_de_evaluacion';
    case EvaluacionesNoRegistradas = 'evaluaciones_no_registradas';
    case ListaDeReposicion = 'lista_de_reposicion';

    public function titulo(): string
    {
        return match ($this) {
            self::UsoDeEscenarios => 'Uso de escenarios clínicos',
            self::ResultadosDeEvaluacion => 'Resultados de evaluación',
            self::EvaluacionesNoRegistradas => 'Evaluaciones no registradas',
            self::ListaDeReposicion => 'Lista de insumos por pedir o reponer',
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
