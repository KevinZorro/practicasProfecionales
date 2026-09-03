<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('casos_clinicos', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->string('nombre');
            $tabla->text('descripcion');
            $tabla->string('imagen')->nullable();
            $tabla->boolean('visible_publico')->default(false);
            $tabla->unsignedInteger('orden')->default(0);
            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();

            // La landing lista los casos visibles ordenados.
            $tabla->index(['visible_publico', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('casos_clinicos');
    }
};
