<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Control de acceso de participantes (RF68, RF69).
 *
 * bloqueos: coordinación bloquea a un estudiante o docente para el uso del
 * laboratorio. El bloqueo no se borra al levantarlo: se anota quién, cuándo
 * y por qué, así que la tabla es también el historial. A lo sumo un bloqueo
 * vigente por persona, garantizado por un índice único parcial.
 *
 * estudiante_solicitud gana el retiro: quien sale de una sesión no se borra
 * de la lista, se marca con el motivo y el responsable (RF69).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bloqueos', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $tabla->text('motivo');
            $tabla->foreignId('bloqueado_por')->constrained('users')->restrictOnDelete();
            $tabla->timestamp('levantado_at')->nullable();
            $tabla->foreignId('levantado_por')->nullable()->constrained('users')->restrictOnDelete();
            $tabla->text('motivo_levantamiento')->nullable();
            $tabla->timestamps();

            $tabla->index(['user_id', 'levantado_at']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX bloqueos_uno_vigente_por_persona ON bloqueos (user_id) WHERE levantado_at IS NULL',
        );

        Schema::table('estudiante_solicitud', function (Blueprint $tabla): void {
            $tabla->timestamp('retirado_at')->nullable();
            $tabla->foreignId('retirado_por')->nullable()->constrained('users')->restrictOnDelete();
            $tabla->text('motivo_retiro')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('estudiante_solicitud', function (Blueprint $tabla): void {
            $tabla->dropConstrainedForeignId('retirado_por');
            $tabla->dropColumn(['retirado_at', 'motivo_retiro']);
        });

        Schema::dropIfExists('bloqueos');
    }
};
