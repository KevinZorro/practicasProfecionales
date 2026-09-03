<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caso_clinico_capacidad', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('caso_clinico_id')->constrained('casos_clinicos')->cascadeOnDelete();
            $tabla->foreignId('capacidad_id')->constrained('capacidades')->cascadeOnDelete();

            $tabla->unique(['caso_clinico_id', 'capacidad_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caso_clinico_capacidad');
    }
};
