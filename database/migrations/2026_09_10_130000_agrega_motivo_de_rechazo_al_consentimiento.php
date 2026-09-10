<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Al rechazar un consentimiento el estudiante tiene que volver a subirlo, y
 * necesita saber por qué. El modelo relacional no preveía dónde guardarlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consentimientos_estudiante', function (Blueprint $tabla): void {
            $tabla->text('motivo_rechazo')->nullable()->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('consentimientos_estudiante', function (Blueprint $tabla): void {
            $tabla->dropColumn('motivo_rechazo');
        });
    }
};
