<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estado funcional del inventario y su historial (RF66).
 *
 * La columna "estado" ya guardaba el estado funcional, solo que con nombres
 * de disponibilidad: "disponible" significaba "funciona" —el Service lo leía
 * en un método llamado estaOperativo()— y "mantenimiento" y "baja" nunca
 * fueron estados de disponibilidad. Así que se renombran los valores en vez
 * de abrir una segunda columna de estado que diría casi lo mismo.
 *
 * Los ítems existentes quedan operativos (RF66.5). Los que estaban en
 * mantenimiento pasan a revisión y los dados de baja siguen de baja: no se
 * resucita un ítem que el laboratorio ya descartó.
 *
 * El historial va en tabla aparte, no en columnas del ítem: el flujo tiene
 * varias transiciones y es de ida y vuelta (revisión → operativo → revisión
 * otra vez), y unas columnas solo guardarían la última. El estado actual se
 * queda además en el ítem porque lo lee cada cálculo de disponibilidad, y
 * resolverlo con una subconsulta del último registro sería una trampa de
 * rendimiento. Los dos se escriben en la misma transacción.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('items_inventario')->where('estado', 'disponible')->update(['estado' => 'operativo']);
        DB::table('items_inventario')->where('estado', 'mantenimiento')->update(['estado' => 'en_revision']);
        DB::table('items_inventario')->where('estado', 'baja')->update(['estado' => 'dado_de_baja']);

        Schema::table('items_inventario', function (Blueprint $tabla): void {
            $tabla->string('estado')->default('operativo')->change();
        });

        Schema::create('cambios_estado_item', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('item_inventario_id')->constrained('items_inventario')->cascadeOnDelete();
            $tabla->string('estado_anterior')->nullable();
            $tabla->string('estado_nuevo');
            $tabla->text('motivo');
            $tabla->foreignId('registrado_por')->constrained('users')->restrictOnDelete();
            $tabla->timestamps();

            // El historial se lee siempre por ítem y del más reciente al más
            // antiguo.
            $tabla->index(['item_inventario_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cambios_estado_item');

        DB::table('items_inventario')->where('estado', 'operativo')->update(['estado' => 'disponible']);
        DB::table('items_inventario')->where('estado', 'en_revision')->update(['estado' => 'mantenimiento']);
        DB::table('items_inventario')->where('estado', 'defectuoso')->update(['estado' => 'mantenimiento']);
        DB::table('items_inventario')->where('estado', 'dado_de_baja')->update(['estado' => 'baja']);

        Schema::table('items_inventario', function (Blueprint $tabla): void {
            $tabla->string('estado')->default('disponible')->change();
        });
    }
};
