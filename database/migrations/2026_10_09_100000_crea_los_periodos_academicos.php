<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Periodos académicos que abre y cierra el laboratorio (RF75).
 *
 * Reemplaza el cálculo por calendario: el sistema no impone fechas. El
 * nombre lo escriben ellos ("2026-2") y es el mismo texto que guarda
 * formatos_confidencialidad.periodo_academico, así que no se repite.
 *
 * A lo sumo uno abierto a la vez: lo garantiza un índice único parcial, que
 * no se salta ni desde tinker ni con dos personas abriendo a la vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodos_academicos', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->string('nombre', 20)->unique();
            $tabla->timestamp('abierto_at');
            $tabla->foreignId('abierto_por')->constrained('users')->restrictOnDelete();
            $tabla->timestamp('cerrado_at')->nullable();
            $tabla->foreignId('cerrado_por')->nullable()->constrained('users')->restrictOnDelete();
            $tabla->timestamps();

            $tabla->index('cerrado_at');
        });

        DB::statement(
            'CREATE UNIQUE INDEX periodos_academicos_un_solo_abierto ON periodos_academicos ((true)) WHERE cerrado_at IS NULL',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('periodos_academicos');
    }
};
