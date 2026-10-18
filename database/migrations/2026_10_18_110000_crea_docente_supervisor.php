<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Qué docentes supervisa cada docente supervisor (RF77). El ADMIN decide la
 * agrupación; unos siete docentes por supervisor, sin tope, y un docente
 * puede tener varios supervisores. Es permanente hasta que el ADMIN la
 * cambie (D22).
 *
 * Los dos lados son cuentas de users. Borrar una cuenta no se permite si
 * está en una agrupación: la agrupación es histórico de quién vio qué.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docente_supervisor', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('supervisor_id')->constrained('users')->restrictOnDelete();
            $tabla->foreignId('docente_id')->constrained('users')->restrictOnDelete();
            $tabla->foreignId('asignado_por')->constrained('users')->restrictOnDelete();
            $tabla->timestamps();

            $tabla->unique(['supervisor_id', 'docente_id']);
            $tabla->index('docente_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docente_supervisor');
    }
};
