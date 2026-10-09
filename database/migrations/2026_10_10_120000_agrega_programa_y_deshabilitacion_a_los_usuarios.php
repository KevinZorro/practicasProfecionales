<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programa académico y deshabilitación manual de usuarios (RF19, RF22).
 *
 * "programa" lo trae la sincronización institucional: el acceso es para los
 * cuatro programas que usan el laboratorio (RF19), y el estado del formato
 * se agrupa por programa (RF53).
 *
 * La deshabilitación que hace el ADMIN es una marca aparte de "estado"
 * (D6 de docs/trazabilidad.md): "estado" es la vigencia institucional y lo
 * escribe solo la sincronización (regla 8). Si el ADMIN deshabilita a
 * alguien vigente, la siguiente sincronización no lo reactiva, porque no
 * toca estas columnas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $tabla): void {
            $tabla->string('programa')->nullable();
            $tabla->timestamp('deshabilitado_at')->nullable();
            $tabla->foreignId('deshabilitado_por')->nullable()->constrained('users')->restrictOnDelete();
            $tabla->text('motivo_deshabilitacion')->nullable();

            $tabla->index('programa');
            $tabla->index('documento');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $tabla): void {
            $tabla->dropIndex(['programa']);
            $tabla->dropIndex(['documento']);
            $tabla->dropConstrainedForeignId('deshabilitado_por');
            $tabla->dropColumn(['programa', 'deshabilitado_at', 'motivo_deshabilitacion']);
        });
    }
};
