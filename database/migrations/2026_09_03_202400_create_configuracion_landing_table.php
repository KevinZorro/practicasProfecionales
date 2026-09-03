<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pares clave-valor del contenido editable de la landing: video del hero,
 * textos, datos de contacto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_landing', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->string('clave')->unique();
            $tabla->text('valor')->nullable();
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_landing');
    }
};
