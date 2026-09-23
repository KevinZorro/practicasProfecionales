<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para las cuatro llaves foráneas que el código sí filtra o cruza.
 *
 * PostgreSQL no indexa solo las llaves foráneas. De las que quedaron sin
 * índice, casi todas son de rastro —quién asignó, quién verificó— y nunca se
 * filtran, así que no lo necesitan. Estas cuatro sí:
 *
 * - evaluaciones.docente_id: el historial del docente (RF50).
 * - evaluaciones.tipo_evaluacion_id: el cálculo del número de intento
 *   (RF48), que cuenta las evaluaciones previas del mismo tipo.
 * - solicitud_item.item_inventario_id: la disponibilidad por franja, que
 *   cruza cada ítem con lo comprometido en solicitudes aprobadas. El índice
 *   único (solicitud_id, item_inventario_id) empieza por solicitud_id y no
 *   sirve para buscar por ítem.
 * - solicitudes.caso_clinico_id: los reportes de uso de escenarios.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluaciones', function (Blueprint $tabla): void {
            $tabla->index('docente_id');
            $tabla->index('tipo_evaluacion_id');
        });

        Schema::table('solicitud_item', function (Blueprint $tabla): void {
            $tabla->index('item_inventario_id');
        });

        Schema::table('solicitudes', function (Blueprint $tabla): void {
            $tabla->index('caso_clinico_id');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes', function (Blueprint $tabla): void {
            $tabla->dropIndex(['caso_clinico_id']);
        });

        Schema::table('solicitud_item', function (Blueprint $tabla): void {
            $tabla->dropIndex(['item_inventario_id']);
        });

        Schema::table('evaluaciones', function (Blueprint $tabla): void {
            $tabla->dropIndex(['docente_id']);
            $tabla->dropIndex(['tipo_evaluacion_id']);
        });
    }
};
