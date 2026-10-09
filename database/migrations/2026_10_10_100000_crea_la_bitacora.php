<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de auditoría (RF62): aprobaciones, rechazos, reprogramaciones,
 * sustituciones, retiros, bloqueos y cambios de rol, con quién, cuándo y
 * por qué.
 *
 * Una sola tabla, de solo añadir, que escribe cada Service dentro de la
 * misma transacción que la acción. Los rastros propios de cada módulo
 * (asignaciones_de_rol, cambios_estado_item, reprogramaciones…) siguen
 * siendo la fuente de cada uno; esta es la que se consulta de una vez.
 *
 * "descripcion" se guarda escrita, no se arma al leer: si mañana cambia el
 * nombre del caso clínico o del docente, la bitácora sigue diciendo lo que
 * pasó con los nombres de ese día.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->string('accion');
            $tabla->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $tabla->morphs('auditable');
            $tabla->text('descripcion');
            $tabla->text('motivo')->nullable();
            $tabla->timestamp('created_at');

            $tabla->index(['accion', 'created_at']);
            $tabla->index(['user_id', 'created_at']);
            $tabla->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora');
    }
};
