<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('titulos_docente', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('perfil_docente_id')->constrained('perfiles_docentes')->cascadeOnDelete();
            $tabla->string('titulo');
            $tabla->string('institucion');
            $tabla->unsignedInteger('orden')->default(0);
            $tabla->timestamps();

            $tabla->index(['perfil_docente_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('titulos_docente');
    }
};
