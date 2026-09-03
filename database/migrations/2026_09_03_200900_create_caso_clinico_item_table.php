<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventario que un caso clínico necesita. Es la tabla que permite precargar
 * los equipos al crear una solicitud.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caso_clinico_item', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('caso_clinico_id')->constrained('casos_clinicos')->cascadeOnDelete();
            // El inventario se da de baja con "activo", nunca se borra.
            $tabla->foreignId('item_inventario_id')->constrained('items_inventario')->restrictOnDelete();
            $tabla->unsignedInteger('cantidad')->default(1);

            $tabla->unique(['caso_clinico_id', 'item_inventario_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caso_clinico_item');
    }
};
