<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ninguna evaluación existe sin escenario apartado: solicitud_id es
 * obligatorio y único. Que la solicitud sea de tipo evaluación y esté
 * aprobada lo valida EvaluacionService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluaciones', function (Blueprint $tabla): void {
            $tabla->id();
            // restrictOnDelete, no cascade: borrar una solicitud con
            // evaluación registrada destruiría el histórico académico.
            $tabla->foreignId('solicitud_id')->unique()->constrained('solicitudes')->restrictOnDelete();
            $tabla->foreignId('tipo_evaluacion_id')->constrained('tipos_evaluacion')->restrictOnDelete();
            $tabla->foreignId('docente_id')->constrained('users')->restrictOnDelete();
            $tabla->string('estado')->default('borrador');
            $tabla->timestamps();

            $tabla->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluaciones');
    }
};
