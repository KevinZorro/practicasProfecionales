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

    /**
     * Los que salen en la pantalla de reportes (RF54-RF56). La lista de
     * reposición tiene su propia pantalla, y sus descargas son solo de
     * listas cerradas (RF67): no se puede pedir por la ruta de estos.
     *
     * @return list<self>
     */
    public static function agregados(): array
    {
        return [self::UsoDeEscenarios, self::ResultadosDeEvaluacion, self::EvaluacionesNoRegistradas];
    }

    /**
     * Solo el uso de escenarios sabe de salas: la sala se asigna en la
     * preparación (regla 4), y ni el resultado de una evaluación ni una
     * evaluación faltante dependen de ella.
     */
    public function filtraPorSala(): bool
    {
        return $this === self::UsoDeEscenarios;
    }

    /** Qué cuenta el reporte, para quien lo elige en la pantalla. */
    public function descripcion(): string
    {
        return match ($this) {
            self::UsoDeEscenarios => 'Sesiones, horas y estudiantes por docente, materia, caso clínico, sala y tipo de sesión. Solo solicitudes aprobadas.',
            self::ResultadosDeEvaluacion => 'Un renglón por estudiante evaluado, con su intento y su resultado. Solo evaluaciones finalizadas.',
            self::EvaluacionesNoRegistradas => 'Escenarios de evaluación que ya pasaron y cuya evaluación no se registró o quedó en borrador.',
            self::ListaDeReposicion => 'Insumos por pedir o reponer de una lista cerrada.',
        };
    }

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
