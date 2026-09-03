<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Copia congelada de items_checklist en el momento de crear la evaluación.
 * La duplicación es intencional: si el ADMIN edita la plantilla después, las
 * evaluaciones históricas conservan los ítems con los que se evaluó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluacion_items', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('evaluacion_id')->constrained('evaluaciones')->cascadeOnDelete();
            $tabla->text('descripcion');
            $tabla->unsignedInteger('orden')->default(0);
            $tabla->timestamps();

            $tabla->index(['evaluacion_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluacion_items');
    }
};
