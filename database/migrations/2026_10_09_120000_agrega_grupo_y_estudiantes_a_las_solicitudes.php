<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grupo y estudiantes de cada sesión (RF28).
 *
 * El grupo es la subdivisión de la clase que pasa a los simuladores (A, B,
 * C…). No hay grupos fijos del semestre: el docente dice al solicitar qué
 * estudiantes van a esa sesión, y esa lista es la que se revisa en la puerta
 * (RF70) y de la que salen los evaluados.
 *
 * "grupo" es nulable por las solicitudes que ya existen; el formulario del
 * docente lo exige desde ahora.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes', function (Blueprint $tabla): void {
            $tabla->string('grupo', 2)->nullable();
        });

        Schema::create('estudiante_solicitud', function (Blueprint $tabla): void {
            // cascade: la lista no tiene sentido sin la sesión. restrict con
            // el estudiante: los usuarios no se borran, se desactivan.
            $tabla->foreignId('solicitud_id')->constrained('solicitudes')->cascadeOnDelete();
            $tabla->foreignId('estudiante_id')->constrained('users')->restrictOnDelete();
            $tabla->timestamps();

            $tabla->primary(['solicitud_id', 'estudiante_id']);
            $tabla->index('estudiante_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estudiante_solicitud');

        Schema::table('solicitudes', function (Blueprint $tabla): void {
            $tabla->dropColumn('grupo');
        });
    }
};
