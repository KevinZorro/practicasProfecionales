<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Equipamiento que la portada presenta como protagonista: los simuladores,
 * la sala inmersiva, la mesa de anatomía virtual (RF01, RF10).
 *
 * No es el inventario: aquí no hay unidades ni estado funcional, solo lo que
 * el ADMIN quiere mostrar al público. "caracteristicas" es una lista corta
 * de frases; no se llama "capacidades" porque esa palabra ya nombra tres
 * cosas distintas en este dominio (§1 del CLAUDE.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipos_destacados', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->string('nombre', 120);
            $tabla->string('resumen', 200);
            $tabla->string('imagen')->nullable();
            $tabla->jsonb('caracteristicas')->default('[]');
            $tabla->unsignedInteger('orden')->default(0);
            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();

            $tabla->index(['activo', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipos_destacados');
    }
};
