<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Video opcional en galería, escenarios, talleres, eventos y equipamiento
 * destacado (RF76). Es la ruta de un archivo en el disco público, como las
 * imágenes. Una pieza de la galería puede ser solo video, así que su foto
 * deja de ser obligatoria, pero la base sigue exigiendo que tenga una cosa
 * u otra.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Una llamada por tabla, escrita a mano: Larastan lee las migraciones
        // sin ejecutarlas y no resuelve un nombre de tabla que viene de una variable.
        Schema::table('casos_clinicos', function (Blueprint $tabla): void {
            $tabla->string('video')->nullable();
        });
        Schema::table('talleres', function (Blueprint $tabla): void {
            $tabla->string('video')->nullable();
        });
        Schema::table('eventos', function (Blueprint $tabla): void {
            $tabla->string('video')->nullable();
        });
        Schema::table('equipos_destacados', function (Blueprint $tabla): void {
            $tabla->string('video')->nullable();
        });
        Schema::table('galeria_fotos', function (Blueprint $tabla): void {
            $tabla->string('video')->nullable();
            $tabla->string('imagen_path')->nullable()->change();
        });
        DB::statement('ALTER TABLE galeria_fotos ADD CONSTRAINT galeria_fotos_con_foto_o_video CHECK (imagen_path IS NOT NULL OR video IS NOT NULL)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE galeria_fotos DROP CONSTRAINT galeria_fotos_con_foto_o_video');
        DB::table('galeria_fotos')->whereNull('imagen_path')->delete();

        Schema::table('galeria_fotos', function (Blueprint $tabla): void {
            $tabla->string('imagen_path')->nullable(false)->change();
        });

        Schema::table('casos_clinicos', function (Blueprint $tabla): void {
            $tabla->dropColumn('video');
        });
        Schema::table('talleres', function (Blueprint $tabla): void {
            $tabla->dropColumn('video');
        });
        Schema::table('eventos', function (Blueprint $tabla): void {
            $tabla->dropColumn('video');
        });
        Schema::table('equipos_destacados', function (Blueprint $tabla): void {
            $tabla->dropColumn('video');
        });
        Schema::table('galeria_fotos', function (Blueprint $tabla): void {
            $tabla->dropColumn('video');
        });
    }
};
