<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simuladores y equipos comparten atributos y se solicitan igual, así que
 * viven en una sola tabla con el discriminador "tipo". nivel_fidelidad
 * queda nulo en los equipos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items_inventario', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->string('nombre');
            $tabla->string('tipo');
            $tabla->string('nivel_fidelidad')->nullable();
            $tabla->unsignedInteger('cantidad_total')->default(0);
            $tabla->text('descripcion')->nullable();
            $tabla->string('estado')->default('disponible');
            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();

            $tabla->index(['tipo', 'estado']);
            $tabla->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items_inventario');
    }
};
