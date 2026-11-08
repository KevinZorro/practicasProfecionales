<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que coordinación le dice al docente al aprobar. Obligatorio si faltan
 * unidades en esa franja: el docente tiene que saber qué no va a encontrar
 * (decisión del cliente del 2026-11-07).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes', function (Blueprint $tabla): void {
            $tabla->text('observaciones_aprobacion')->nullable()->after('motivo_rechazo');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes', function (Blueprint $tabla): void {
            $tabla->dropColumn('observaciones_aprobacion');
        });
    }
};
