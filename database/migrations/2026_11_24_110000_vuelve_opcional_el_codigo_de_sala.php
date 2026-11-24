<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El laboratorio no identifica sus salas con un código, sino por el nombre y
 * la ubicación (decisión del cliente del 2026-11-24). El código queda como
 * dato opcional. El índice único se conserva: PostgreSQL admite varios NULL
 * en una columna única, así que solo choca un código repetido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salas', function (Blueprint $tabla): void {
            $tabla->string('codigo')->nullable()->change();
        });
    }

    /**
     * Al deshacer, una sala sin código recibe uno provisional (SALA-<id>)
     * para poder volver a NOT NULL sin perder la fila.
     */
    public function down(): void
    {
        DB::table('salas')->whereNull('codigo')->update(['codigo' => DB::raw("'SALA-' || id")]);

        Schema::table('salas', function (Blueprint $tabla): void {
            $tabla->string('codigo')->nullable(false)->change();
        });
    }
};
