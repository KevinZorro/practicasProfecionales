<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Accesorios y repuestos de un simulador concreto (RF38).
 *
 * Son un tipo de ítem más ("accesorio") que apunta al simulador al que
 * pertenecen. El CHECK lo amarra en los dos sentidos: un accesorio siempre
 * tiene simulador, y nada que no sea accesorio lo tiene. Que ese simulador
 * sea de verdad un simulador lo comprueba InventarioService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items_inventario', function (Blueprint $tabla): void {
            $tabla->foreignId('simulador_id')->nullable()->constrained('items_inventario')->restrictOnDelete();
            $tabla->index('simulador_id');
        });

        DB::statement(
            "ALTER TABLE items_inventario ADD CONSTRAINT items_inventario_accesorio_con_simulador CHECK ((tipo = 'accesorio') = (simulador_id IS NOT NULL))",
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE items_inventario DROP CONSTRAINT items_inventario_accesorio_con_simulador');

        Schema::table('items_inventario', function (Blueprint $tabla): void {
            $tabla->dropConstrainedForeignId('simulador_id');
        });
    }
};
