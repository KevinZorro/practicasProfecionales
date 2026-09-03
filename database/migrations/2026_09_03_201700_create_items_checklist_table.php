<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plantilla maestra del checklist, definida por el ADMIN. El docente no la
 * edita: al crear una evaluación estos ítems se copian a evaluacion_items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items_checklist', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('tipo_evaluacion_id')->constrained('tipos_evaluacion')->cascadeOnDelete();
            $tabla->text('descripcion');
            $tabla->unsignedInteger('orden')->default(0);
            $tabla->timestamps();

            $tabla->index(['tipo_evaluacion_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items_checklist');
    }
};
