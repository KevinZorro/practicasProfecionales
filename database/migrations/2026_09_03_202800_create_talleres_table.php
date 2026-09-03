<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talleres', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->string('titulo');
            $tabla->text('descripcion');
            $tabla->string('imagen')->nullable();
            $tabla->string('tema');
            $tabla->date('fecha');
            $tabla->string('modalidad');
            // Si está en falso, el taller se muestra sin formulario de interés.
            $tabla->boolean('muestra_formulario')->default(true);
            $tabla->unsignedInteger('orden')->default(0);
            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();

            $tabla->index(['activo', 'orden']);
            $tabla->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talleres');
    }
};
