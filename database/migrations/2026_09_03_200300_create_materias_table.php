<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materias', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->string('codigo')->unique();
            $tabla->string('nombre');
            $tabla->unsignedTinyInteger('semestre');
            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();

            $tabla->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materias');
    }
};
