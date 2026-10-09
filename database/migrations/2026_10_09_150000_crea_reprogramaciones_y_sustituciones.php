<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Novedades de una sesión aprobada: reprogramarla (RF61) y sustituir al
 * docente (RF73).
 *
 * Las dos tablas son de solo añadir: guardan lo que había antes, lo nuevo,
 * el motivo y quién lo registró. La solicitud se actualiza en el mismo
 * momento con el valor vigente, igual que asignaciones_de_rol y
 * cambios_estado_item.
 *
 * solicitudes.docente_que_dicta_id es el reemplazo vigente: nulo mientras
 * dicte el titular. El reporte de uso atribuye las horas a quien dictó
 * (RF54), así que no basta con el historial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes', function (Blueprint $tabla): void {
            $tabla->foreignId('docente_que_dicta_id')->nullable()->constrained('users')->restrictOnDelete();
            $tabla->index('docente_que_dicta_id');
        });

        Schema::create('reprogramaciones', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('solicitud_id')->constrained('solicitudes')->restrictOnDelete();
            $tabla->date('fecha_anterior');
            $tabla->time('hora_inicio_anterior');
            $tabla->time('hora_fin_anterior');
            $tabla->foreignId('caso_clinico_anterior_id')->constrained('casos_clinicos')->restrictOnDelete();
            $tabla->date('fecha_nueva');
            $tabla->time('hora_inicio_nueva');
            $tabla->time('hora_fin_nueva');
            $tabla->foreignId('caso_clinico_nuevo_id')->constrained('casos_clinicos')->restrictOnDelete();
            $tabla->text('motivo');
            $tabla->text('constancia_comunicacion');
            $tabla->foreignId('reprogramada_por')->constrained('users')->restrictOnDelete();
            $tabla->timestamp('created_at');

            $tabla->index(['solicitud_id', 'created_at']);
        });

        Schema::create('sustituciones', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('solicitud_id')->constrained('solicitudes')->restrictOnDelete();
            $tabla->foreignId('docente_anterior_id')->constrained('users')->restrictOnDelete();
            $tabla->foreignId('docente_nuevo_id')->constrained('users')->restrictOnDelete();
            $tabla->text('motivo');
            $tabla->foreignId('registrada_por')->constrained('users')->restrictOnDelete();
            $tabla->timestamp('created_at');

            $tabla->index(['solicitud_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sustituciones');
        Schema::dropIfExists('reprogramaciones');

        Schema::table('solicitudes', function (Blueprint $tabla): void {
            $tabla->dropConstrainedForeignId('docente_que_dicta_id');
        });
    }
};
