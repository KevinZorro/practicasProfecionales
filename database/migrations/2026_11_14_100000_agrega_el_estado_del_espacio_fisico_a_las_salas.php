<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RF30: la revisión de una solicitud verifica el estado del espacio físico
 * (aire acondicionado, filtraciones). Es el estado de hoy de cada sala, con
 * quién lo anotó y cuándo; no guarda historial (decisión del 2026-11-14).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salas', function (Blueprint $tabla): void {
            $tabla->string('estado')->default('operativa');
            $tabla->text('novedad')->nullable();
            $tabla->timestamp('estado_actualizado_at')->nullable();
            $tabla->foreignId('estado_actualizado_por')->nullable()->constrained('users')->restrictOnDelete();
        });

        // Lo que no está operativo dice por qué: así la revisión puede avisarlo.
        DB::statement(<<<'SQL'
            ALTER TABLE salas ADD CONSTRAINT salas_estado_valido CHECK (
                estado IN ('operativa', 'con_novedad', 'fuera_de_servicio')
                AND (estado = 'operativa' OR NULLIF(TRIM(novedad), '') IS NOT NULL)
            )
            SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE salas DROP CONSTRAINT IF EXISTS salas_estado_valido');

        Schema::table('salas', function (Blueprint $tabla): void {
            $tabla->dropConstrainedForeignId('estado_actualizado_por');
            $tabla->dropColumn(['estado', 'novedad', 'estado_actualizado_at']);
        });
    }
};
