<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El estado funcional pasa de ser del ítem a ser de sus unidades (RF66.2).
 *
 * El personal reporta en cantidades parciales —"tantas máscaras no
 * sirven"—, así que de ocho sondas dos pueden irse a revisión sin sacar de
 * disponibilidad las seis restantes. La columna "estado" no tenía dónde
 * guardar eso y desaparece: un ítem con seis operativas y dos defectuosas
 * no tiene "un estado", y cualquier valor que se le ponga miente.
 *
 * Los contadores viven en el ítem, no se derivan del historial. Derivarlos
 * obligaría a una agregación sobre una tabla que solo crece cada vez que se
 * calcula la disponibilidad —y eso pasa en el formulario del docente, en la
 * bandeja, en el listado y en cada solicitud—, además de convertir el
 * filtro por estado en un subconsulta agregada. Es el mismo criterio que ya
 * siguen el consentimiento (RF53) y la primera versión del RF66: el valor
 * actual donde se lee, el registro de solo añadir al lado.
 *
 * Lo que impide que los contadores deriven es el CHECK de aquí abajo, que
 * no se puede saltar ni desde tinker ni desde un seeder descuidado.
 *
 * Nota sobre el historial previo: las filas de la primera versión movían el
 * ítem entero, así que su cantidad se rellena con el total que el ítem tenía
 * en ese momento, que es lo que significaban. Los ítems creados antes de
 * esta migración no tienen asiento de alta, de modo que su historial no
 * reconstruye los contadores desde cero; de aquí en adelante sí.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cambios_estado_item', function (Blueprint $tabla): void {
            $tabla->unsignedInteger('cantidad')->default(1)->after('item_inventario_id');
        });

        // Antes de tocar nada: las filas viejas movían el ítem completo.
        DB::statement('
            UPDATE cambios_estado_item AS c
            SET cantidad = i.cantidad_total
            FROM items_inventario AS i
            WHERE i.id = c.item_inventario_id
        ');

        Schema::table('items_inventario', function (Blueprint $tabla): void {
            $tabla->unsignedInteger('cantidad_operativa')->default(0)->after('cantidad_total');
            $tabla->unsignedInteger('cantidad_en_revision')->default(0)->after('cantidad_operativa');
            $tabla->unsignedInteger('cantidad_defectuosa')->default(0)->after('cantidad_en_revision');
        });

        DB::table('items_inventario')->where('estado', 'operativo')
            ->update(['cantidad_operativa' => DB::raw('cantidad_total')]);
        DB::table('items_inventario')->where('estado', 'en_revision')
            ->update(['cantidad_en_revision' => DB::raw('cantidad_total')]);
        DB::table('items_inventario')->where('estado', 'defectuoso')
            ->update(['cantidad_defectuosa' => DB::raw('cantidad_total')]);

        // Lo dado de baja ya no ocupa sitio en el total: esa es la regla
        // nueva, y el historial conserva cuántas unidades se descartaron.
        DB::table('items_inventario')->where('estado', 'dado_de_baja')
            ->update(['cantidad_total' => 0]);

        Schema::table('items_inventario', function (Blueprint $tabla): void {
            $tabla->dropIndex(['tipo', 'estado']);
            $tabla->dropColumn('estado');
            // Lo que se filtra ahora es "¿le quedan unidades operativas?".
            $tabla->index(['tipo', 'cantidad_operativa']);
        });

        DB::statement('
            ALTER TABLE items_inventario
            ADD CONSTRAINT items_inventario_cantidades_cuadran
            CHECK (cantidad_total = cantidad_operativa + cantidad_en_revision + cantidad_defectuosa)
        ');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE items_inventario DROP CONSTRAINT items_inventario_cantidades_cuadran');

        Schema::table('items_inventario', function (Blueprint $tabla): void {
            $tabla->dropIndex(['tipo', 'cantidad_operativa']);
            $tabla->string('estado')->default('operativo');
        });

        // Al volver atrás el ítem recupera un único estado: el del grueso de
        // sus unidades. Es lo mejor que se puede hacer, porque la columna no
        // admite el desglose.
        DB::statement("
            UPDATE items_inventario
            SET estado = CASE
                WHEN cantidad_total = 0 THEN 'dado_de_baja'
                WHEN cantidad_defectuosa >= cantidad_operativa AND cantidad_defectuosa >= cantidad_en_revision THEN 'defectuoso'
                WHEN cantidad_en_revision >= cantidad_operativa THEN 'en_revision'
                ELSE 'operativo'
            END
        ");

        Schema::table('items_inventario', function (Blueprint $tabla): void {
            $tabla->index(['tipo', 'estado']);
            $tabla->dropColumn(['cantidad_operativa', 'cantidad_en_revision', 'cantidad_defectuosa']);
        });

        Schema::table('cambios_estado_item', function (Blueprint $tabla): void {
            $tabla->dropColumn('cantidad');
        });
    }
};
