<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF17 modificado: los videos de la galería institucional se suben al
 * servidor, con su portada, y no son enlaces (D20). La columna `url` nunca
 * tuvo uso: la tabla se creó antes de que el enunciado fijara la subida.
 *
 * El video queda nulable en la base, igual que las fotos del contenido
 * público: si el archivo se pierde, PortadaService no lo pinta, y el
 * formulario de Filament lo exige.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos_institucionales', function (Blueprint $tabla): void {
            $tabla->dropColumn('url');
            $tabla->string('video')->nullable();
            $tabla->string('portada')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('videos_institucionales', function (Blueprint $tabla): void {
            $tabla->dropColumn(['video', 'portada']);
            $tabla->string('url')->default('');
        });
    }
};
