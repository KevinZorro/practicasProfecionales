<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * No lleva sala_id a propósito: la sala la asigna el administrativo durante
 * la preparación, después de que el coordinador aprueba.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes', function (Blueprint $tabla): void {
            $tabla->id();
            // Los usuarios se desactivan con "estado", nunca se borran: el
            // histórico de solicitudes tiene que sobrevivir.
            $tabla->foreignId('docente_id')->constrained('users')->restrictOnDelete();
            $tabla->foreignId('materia_id')->constrained('materias')->restrictOnDelete();
            $tabla->foreignId('caso_clinico_id')->constrained('casos_clinicos')->restrictOnDelete();
            $tabla->string('tipo');
            $tabla->date('fecha');
            $tabla->time('hora_inicio');
            $tabla->time('hora_fin');
            $tabla->unsignedInteger('cantidad_estudiantes');
            $tabla->string('estado')->default('pendiente');
            $tabla->foreignId('revisada_por')->nullable()->constrained('users')->restrictOnDelete();
            $tabla->timestamp('revisada_at')->nullable();
            $tabla->foreignId('resuelta_por')->nullable()->constrained('users')->restrictOnDelete();
            $tabla->timestamp('resuelta_at')->nullable();
            $tabla->text('motivo_rechazo')->nullable();
            $tabla->text('observaciones')->nullable();
            $tabla->timestamps();

            $tabla->index(['fecha', 'estado']);
            $tabla->index('docente_id');
            $tabla->index('materia_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes');
    }
};
