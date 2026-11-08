<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Una sesión puede ocupar de una a tres salas (decisión del cliente del
 * 2026-11-08): las salas se separan con cortinas de hospital, y abriéndolas
 * dos o tres son un solo salón. La sala deja de ser una columna de la
 * preparación y pasa a este pivote. El máximo de tres lo pone
 * PreparacionService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preparacion_sala', function (Blueprint $tabla): void {
            $tabla->foreignId('preparacion_id')->constrained('preparaciones')->cascadeOnDelete();
            // Las salas se desactivan, no se borran: borrar una rompería el histórico.
            $tabla->foreignId('sala_id')->constrained('salas')->restrictOnDelete();
            $tabla->primary(['preparacion_id', 'sala_id']);
            // La ocupación de una sala se busca por la sala.
            $tabla->index('sala_id');
        });

        DB::statement('INSERT INTO preparacion_sala (preparacion_id, sala_id) SELECT id, sala_id FROM preparaciones WHERE sala_id IS NOT NULL');

        Schema::table('preparaciones', function (Blueprint $tabla): void {
            $tabla->dropIndex(['sala_id', 'estado']);
            $tabla->dropConstrainedForeignId('sala_id');
        });
    }

    /**
     * El esquema anterior admite una sola sala: al deshacer queda la de id
     * más bajo de cada preparación, y las otras se pierden.
     */
    public function down(): void
    {
        Schema::table('preparaciones', function (Blueprint $tabla): void {
            $tabla->foreignId('sala_id')->nullable()->after('solicitud_id')->constrained('salas')->restrictOnDelete();
            $tabla->index(['sala_id', 'estado']);
        });

        DB::statement('UPDATE preparaciones SET sala_id = (SELECT MIN(sala_id) FROM preparacion_sala WHERE preparacion_sala.preparacion_id = preparaciones.id)');

        Schema::dropIfExists('preparacion_sala');
    }
};
