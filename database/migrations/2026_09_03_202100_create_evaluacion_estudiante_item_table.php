<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluacion_estudiante_item', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('evaluacion_estudiante_id')->constrained('evaluacion_estudiantes')->cascadeOnDelete();
            $tabla->foreignId('evaluacion_item_id')->constrained('evaluacion_items')->cascadeOnDelete();
            $tabla->boolean('cumplido')->default(false);

            $tabla->unique(['evaluacion_estudiante_id', 'evaluacion_item_id'], 'evaluacion_estudiante_item_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluacion_estudiante_item');
    }
};
