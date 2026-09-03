<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "resultado" lo decide el docente y es independiente de los ítems marcados:
 * el sistema no lo calcula. "intento" lo resuelve EvaluacionService contando
 * las evaluaciones previas del estudiante para el mismo tipo de evaluación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluacion_estudiantes', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('evaluacion_id')->constrained('evaluaciones')->cascadeOnDelete();
            $tabla->foreignId('estudiante_id')->constrained('users')->restrictOnDelete();
            $tabla->string('resultado');
            $tabla->unsignedInteger('intento')->default(1);
            $tabla->text('observaciones')->nullable();
            $tabla->timestamps();

            $tabla->unique(['evaluacion_id', 'estudiante_id']);
            $tabla->index('estudiante_id');
            $tabla->index('evaluacion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluacion_estudiantes');
    }
};
