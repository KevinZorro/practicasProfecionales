<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salas', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->string('nombre');
            $tabla->string('codigo')->unique();
            $tabla->unsignedInteger('capacidad');
            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();

            $tabla->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salas');
    }
};
