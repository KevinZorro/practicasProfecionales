<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Capacidad máxima de estudiantes de un escenario clínico (RF74).
 *
 * Nullable a propósito. Ya hay casos clínicos creados y el cliente solo dio
 * dos valores de referencia (morfofisiología 15, el resto 7), así que
 * rellenar por migración sería inventar el dato de escenarios que nadie ha
 * revisado. Una capacidad equivocada por lo bajo bloquea clases reales, que
 * es peor que no tener límite mientras el ADMIN los va registrando. Null se
 * lee como "todavía sin definir", igual que nivel_fidelidad en el
 * inventario.
 *
 * El nombre no es "capacidad" a secas porque en este dominio ya existe otra
 * cosa con ese nombre: la tabla capacidades guarda las capacidades clínicas
 * del simulador (sangrado, llanto, signos vitales).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('casos_clinicos', function (Blueprint $tabla): void {
            $tabla->unsignedInteger('capacidad_maxima_estudiantes')->nullable()->after('descripcion');
        });
    }

    public function down(): void
    {
        Schema::table('casos_clinicos', function (Blueprint $tabla): void {
            $tabla->dropColumn('capacidad_maxima_estudiantes');
        });
    }
};
