<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $tabla): void {
            $tabla->renameColumn('name', 'nombre');
        });

        Schema::table('users', function (Blueprint $tabla): void {
            $tabla->string('google_id')->nullable()->unique()->after('id');
            $tabla->string('documento')->nullable()->after('nombre');
            $tabla->string('codigo_institucional')->nullable()->after('documento');
            $tabla->string('estado')->default('activo')->after('codigo_institucional');
            $tabla->string('origen')->nullable()->after('estado');
            $tabla->timestamp('ultima_sincronizacion')->nullable()->after('origen');

            // La autenticación es Google OAuth: los usuarios que llegan por
            // sincronización institucional no tienen contraseña local.
            $tabla->string('password')->nullable()->change();

            $tabla->index('estado');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $tabla): void {
            $tabla->dropIndex(['estado']);
            $tabla->dropUnique(['google_id']);
            $tabla->dropColumn([
                'google_id',
                'documento',
                'codigo_institucional',
                'estado',
                'origen',
                'ultima_sincronizacion',
            ]);
        });

        Schema::table('users', function (Blueprint $tabla): void {
            $tabla->renameColumn('nombre', 'name');
        });
    }
};
