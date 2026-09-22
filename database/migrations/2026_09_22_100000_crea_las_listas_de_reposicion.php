<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lista de insumos por pedir o reponer (RF67).
 *
 * La lista es un documento que se cierra, no una vista que se recalcula. Es
 * el soporte de la carta de solicitud de compra que el laboratorio presenta
 * al final del semestre: una vez presentada, sus cifras son las que se
 * presentaron, y no pueden cambiar porque después alguien corrija un
 * movimiento del historial. Mismo criterio que los ítems del checklist, que
 * se copian en la evaluación en vez de referenciarse (§4.3 de CLAUDE.md).
 *
 * Mientras está en borrador no guarda líneas: la pantalla calcula la
 * previsualización en el momento. Al cerrarla, esas líneas se congelan aquí
 * —incluido el nombre del ítem, para que renombrarlo después no cambie la
 * carta ya entregada— y a partir de ahí las exportaciones leen de esta
 * tabla, nunca del historial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listas_reposicion', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->date('desde');
            $tabla->date('hasta');
            $tabla->text('observaciones')->nullable();
            $tabla->foreignId('cerrada_por')->nullable()->constrained('users')->restrictOnDelete();
            $tabla->timestamp('cerrada_at')->nullable();
            $tabla->timestamps();

            $tabla->index('cerrada_at');
        });

        Schema::create('necesidades_reposicion', function (Blueprint $tabla): void {
            $tabla->id();
            // Nulo cuando lo que hizo falta todavía no existe en el
            // catálogo, que es el caso que el cliente puso de ejemplo: una
            // pila CR2032 que nunca se tuvo.
            $tabla->foreignId('item_inventario_id')->nullable()->constrained('items_inventario')->restrictOnDelete();
            $tabla->string('descripcion')->nullable();
            $tabla->unsignedInteger('cantidad');
            $tabla->text('justificacion');
            $tabla->date('fecha');
            $tabla->foreignId('registrada_por')->constrained('users')->restrictOnDelete();
            $tabla->timestamps();

            $tabla->index('fecha');
        });

        // Una necesidad apunta a un ítem del catálogo o trae su propia
        // descripción; sin una de las dos no se sabe qué hay que comprar.
        DB::statement('
            ALTER TABLE necesidades_reposicion
            ADD CONSTRAINT necesidades_reposicion_saben_que_piden
            CHECK (item_inventario_id IS NOT NULL OR descripcion IS NOT NULL)
        ');

        Schema::create('lineas_reposicion', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('lista_reposicion_id')->constrained('listas_reposicion')->cascadeOnDelete();
            $tabla->foreignId('item_inventario_id')->nullable()->constrained('items_inventario')->restrictOnDelete();
            // Congelada: el nombre que tenía el ítem el día que se cerró la
            // lista, o la descripción libre de la necesidad.
            $tabla->string('descripcion');
            $tabla->string('motivo');
            $tabla->unsignedInteger('cantidad');
            $tabla->timestamps();

            $tabla->index(['lista_reposicion_id', 'motivo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lineas_reposicion');
        Schema::dropIfExists('necesidades_reposicion');
        Schema::dropIfExists('listas_reposicion');
    }
};
