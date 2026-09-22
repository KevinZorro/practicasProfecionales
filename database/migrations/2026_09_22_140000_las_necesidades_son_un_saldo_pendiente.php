<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una necesidad vive hasta que alguien la atiende, no hasta que termina el
 * periodo (RF67).
 *
 * Si la pila CR2032 no llegó, en el semestre siguiente sigue haciendo falta.
 * Antes se filtraban por fecha igual que los movimientos de inventario, y
 * eso las hacía desaparecer del borrador siguiente en cuanto los rangos
 * dejaban de solaparse.
 *
 * Son dos criterios distintos a propósito: el historial de inventario es un
 * flujo del periodo —una gasa gastada en julio no se vuelve a pedir en
 * diciembre—, y las necesidades son un saldo pendiente.
 *
 * El pivote guarda en qué listas cerradas entró cada necesidad. Una puede
 * entrar en varias, que es justo el caso de lo que se pide dos semestres
 * seguidos y no llega: la pantalla lo usa para decir "pedida en 2 cartas
 * anteriores", que es el argumento para insistir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('necesidades_reposicion', function (Blueprint $tabla): void {
            $tabla->timestamp('atendida_at')->nullable()->after('fecha');
            $tabla->foreignId('atendida_por')->nullable()->after('atendida_at')
                ->constrained('users')->restrictOnDelete();
            $tabla->string('motivo_atencion')->nullable()->after('atendida_por');

            // Lo que entra en cada borrador es lo pendiente.
            $tabla->index('atendida_at');
        });

        Schema::create('lista_reposicion_necesidad', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('lista_reposicion_id')->constrained('listas_reposicion')->cascadeOnDelete();
            $tabla->foreignId('necesidad_reposicion_id')->constrained('necesidades_reposicion')->cascadeOnDelete();

            $tabla->unique(['lista_reposicion_id', 'necesidad_reposicion_id'], 'lista_necesidad_unica');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lista_reposicion_necesidad');

        Schema::table('necesidades_reposicion', function (Blueprint $tabla): void {
            $tabla->dropIndex(['atendida_at']);
            $tabla->dropConstrainedForeignId('atendida_por');
            $tabla->dropColumn(['atendida_at', 'motivo_atencion']);
        });
    }
};
