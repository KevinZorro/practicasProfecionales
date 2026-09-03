<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materia_tipo_evaluacion', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('materia_id')->constrained('materias')->cascadeOnDelete();
            $tabla->foreignId('tipo_evaluacion_id')->constrained('tipos_evaluacion')->cascadeOnDelete();

            $tabla->unique(['materia_id', 'tipo_evaluacion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materia_tipo_evaluacion');
    }
};
