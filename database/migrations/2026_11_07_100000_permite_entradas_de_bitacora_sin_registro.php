<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hay entradas de la bitácora que no apuntan a un registro (B4.2, B4.5): una
 * acción de Livewire negada, de la que solo se sabe qué se pidió, o la
 * exportación de un reporte, que es una consulta con filtros.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bitacora', function (Blueprint $tabla): void {
            $tabla->string('auditable_type')->nullable()->change();
            $tabla->unsignedBigInteger('auditable_id')->nullable()->change();
        });
    }

    /**
     * El esquema anterior no admite entradas sin registro: deshacer esta
     * migración, que solo se hace en desarrollo, las borra.
     */
    public function down(): void
    {
        DB::table('bitacora')->whereNull('auditable_type')->delete();

        Schema::table('bitacora', function (Blueprint $tabla): void {
            $tabla->string('auditable_type')->nullable(false)->change();
            $tabla->unsignedBigInteger('auditable_id')->nullable(false)->change();
        });
    }
};
