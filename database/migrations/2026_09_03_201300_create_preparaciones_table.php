<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uno a uno con la solicitud: se crea al aprobarla. La sala llega nula y la
 * completa el administrativo el día de la práctica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preparaciones', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('solicitud_id')->unique()->constrained('solicitudes')->cascadeOnDelete();
            // Las salas se desactivan con "activo": borrar una borraría el
            // histórico de dónde se montó cada escenario.
            $tabla->foreignId('sala_id')->nullable()->constrained('salas')->restrictOnDelete();
            $tabla->string('estado')->default('pendiente');
            $tabla->foreignId('preparado_por')->nullable()->constrained('users')->restrictOnDelete();
            $tabla->timestamp('preparado_at')->nullable();
            $tabla->text('observaciones')->nullable();
            $tabla->timestamps();

            $tabla->index(['sala_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preparaciones');
    }
};
