<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estadisticas_landing', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->string('etiqueta');
            $tabla->string('valor');
            $tabla->unsignedInteger('orden')->default(0);
            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();

            $tabla->index(['activo', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estadisticas_landing');
    }
};
