<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El consentimiento se entrega una vez por semestre y se renueva al iniciar
 * el siguiente: el índice único por estudiante y periodo lo garantiza.
 *
 * archivo_firmado_path apunta a storage/app/consentimientos, fuera del disco
 * público: contiene datos personales y se sirve por ruta protegida.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consentimientos_estudiante', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('estudiante_id')->constrained('users')->restrictOnDelete();
            $tabla->foreignId('plantilla_id')->constrained('consentimientos_plantilla')->restrictOnDelete();
            $tabla->string('periodo_academico');
            $tabla->string('archivo_firmado_path')->nullable();
            $tabla->string('estado')->default('pendiente');
            $tabla->foreignId('verificado_por')->nullable()->constrained('users')->restrictOnDelete();
            $tabla->timestamp('verificado_at')->nullable();
            $tabla->timestamps();

            $tabla->unique(['estudiante_id', 'periodo_academico']);
            $tabla->index(['periodo_academico', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consentimientos_estudiante');
    }
};
