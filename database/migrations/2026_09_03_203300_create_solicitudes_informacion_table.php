<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interesados que dejan sus datos en el formulario de un taller.
 * restrictOnDelete: los talleres se despublican con "activo", y borrar uno
 * no debe llevarse los contactos recibidos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_informacion', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('taller_id')->constrained('talleres')->restrictOnDelete();
            $tabla->string('nombre');
            $tabla->string('email');
            $tabla->string('telefono')->nullable();
            $tabla->text('mensaje')->nullable();
            $tabla->timestamp('enviado_at')->nullable();
            $tabla->timestamps();

            $tabla->index('taller_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_informacion');
    }
};
