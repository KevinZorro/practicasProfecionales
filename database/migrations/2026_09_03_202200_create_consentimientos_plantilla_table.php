<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consentimientos_plantilla', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->string('nombre');
            $tabla->string('archivo_path');
            $tabla->string('version');
            $tabla->boolean('activo')->default(true);
            $tabla->foreignId('subido_por')->constrained('users')->restrictOnDelete();
            $tabla->timestamps();

            $tabla->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consentimientos_plantilla');
    }
};
