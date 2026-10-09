<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ubicación de las salas y su vínculo con los escenarios (RF65).
 *
 * Cada sala queda en un bloque, un piso y un número de sala dentro de ese
 * bloque y piso, para dar cabida a un edificio nuevo. Son nulables porque
 * las salas que ya existen no los tienen; el formulario del ADMIN los exige
 * desde ahora.
 *
 * ubicaciones_sala es de solo añadir: cada cambio de ubicación deja una fila
 * con quién la registró, así que una reubicación no borra dónde estuvo la
 * sala antes. Mismo patrón que asignaciones_de_rol y cambios_estado_item.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salas', function (Blueprint $tabla): void {
            $tabla->string('bloque', 20)->nullable();
            $tabla->string('piso', 10)->nullable();
            $tabla->string('numero', 10)->nullable();

            // Dos salas no ocupan el mismo sitio. Con nulos, PostgreSQL no
            // compara, así que las salas viejas sin ubicación no chocan.
            $tabla->unique(['bloque', 'piso', 'numero']);
        });

        Schema::create('ubicaciones_sala', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('sala_id')->constrained('salas')->restrictOnDelete();
            $tabla->string('bloque', 20);
            $tabla->string('piso', 10);
            $tabla->string('numero', 10);
            $tabla->foreignId('registrada_por')->constrained('users')->restrictOnDelete();
            $tabla->timestamp('created_at');

            $tabla->index(['sala_id', 'created_at']);
        });

        // Qué escenarios se montan en cada sala. Sin historial: lo que pide
        // conservar el RF65 es la reubicación, y el vínculo solo ordena las
        // salas que se ofrecen al preparar (D14 de docs/trazabilidad.md).
        Schema::create('caso_clinico_sala', function (Blueprint $tabla): void {
            $tabla->foreignId('caso_clinico_id')->constrained('casos_clinicos')->cascadeOnDelete();
            $tabla->foreignId('sala_id')->constrained('salas')->cascadeOnDelete();

            $tabla->primary(['caso_clinico_id', 'sala_id']);
            $tabla->index('sala_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caso_clinico_sala');
        Schema::dropIfExists('ubicaciones_sala');

        Schema::table('salas', function (Blueprint $tabla): void {
            $tabla->dropUnique(['bloque', 'piso', 'numero']);
            $tabla->dropColumn(['bloque', 'piso', 'numero']);
        });
    }
};
