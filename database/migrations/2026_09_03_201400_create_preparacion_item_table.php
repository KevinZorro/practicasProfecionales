<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preparacion_item', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('preparacion_id')->constrained('preparaciones')->cascadeOnDelete();
            $tabla->foreignId('item_inventario_id')->constrained('items_inventario')->restrictOnDelete();
            $tabla->unsignedInteger('cantidad')->default(1);
            $tabla->boolean('alistado')->default(false);

            $tabla->unique(['preparacion_id', 'item_inventario_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preparacion_item');
    }
};
