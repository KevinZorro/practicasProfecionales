<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitud_item', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('solicitud_id')->constrained('solicitudes')->cascadeOnDelete();
            $tabla->foreignId('item_inventario_id')->constrained('items_inventario')->restrictOnDelete();
            $tabla->unsignedInteger('cantidad')->default(1);

            $tabla->unique(['solicitud_id', 'item_inventario_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_item');
    }
};
