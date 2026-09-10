<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El resultado lo decide el docente y no se deriva de los ítems marcados
 * (RF46). Con la columna obligatoria, agregar un estudiante a la evaluación
 * exigía inventarle un resultado de entrada, que es justo el valor por
 * defecto que la regla prohíbe, y "finalizar exige resultado en todos" no
 * podía comprobarse porque nunca faltaba ninguno.
 *
 * Nulo significa "el docente todavía no lo ha decidido".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluacion_estudiantes', function (Blueprint $tabla): void {
            $tabla->string('resultado')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('evaluacion_estudiantes', function (Blueprint $tabla): void {
            $tabla->string('resultado')->nullable(false)->change();
        });
    }
};
