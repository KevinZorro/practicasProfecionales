<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * H3 de la campaña de rendimiento: «quién dicta» una sesión es el reemplazo
 * si lo hay y si no el titular, COALESCE(docente_que_dicta_id, docente_id)
 * (RF73). Así lo filtran Solicitud::scopeQueDicta —sesiones por evaluar,
 * «Hoy» y los cruces del docente— y el reporte de uso de escenarios, y
 * ningún índice de una sola columna sirve para esa expresión.
 *
 * PostgreSQL usa un índice de expresión cuando la consulta escribe la misma
 * expresión: si se cambia el COALESCE en el código (orden de las columnas,
 * otra función), el índice deja de usarse sin dar error.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX solicitudes_docente_que_dicta_efectivo_index ON solicitudes ((COALESCE(docente_que_dicta_id, docente_id)))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS solicitudes_docente_que_dicta_efectivo_index');
    }
};
