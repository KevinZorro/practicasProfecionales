<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ningún contador del inventario puede ser negativo (B4.3 de la auditoría).
 *
 * El CHECK de RF66 controla que la suma cuadre con el total, pero seis
 * operativas, menos una en revisión y una defectuosa también suman seis. En
 * PostgreSQL, unsignedInteger no crea ninguna restricción: este CHECK es la
 * última defensa, que no se salta ni desde tinker ni desde un seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE items_inventario
            ADD CONSTRAINT items_inventario_contadores_no_negativos
            CHECK (cantidad_total >= 0 AND cantidad_operativa >= 0 AND cantidad_en_revision >= 0 AND cantidad_defectuosa >= 0)
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE items_inventario DROP CONSTRAINT items_inventario_contadores_no_negativos');
    }
};
