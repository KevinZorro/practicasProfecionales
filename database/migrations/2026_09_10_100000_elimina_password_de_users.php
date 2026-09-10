<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El RF18 establece que el ingreso es únicamente por cuenta de Google
 * institucional. La columna quedó nullable como vestigio de Breeze, que ya
 * se retiró del proyecto: no hay rutas de contraseña, ni forma de fijarla,
 * ni nada que la lea.
 *
 * remember_token se conserva: lo usa el guard de sesión para el "recordarme"
 * y no depende de la autenticación por contraseña.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $tabla): void {
            $tabla->dropColumn('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $tabla): void {
            $tabla->string('password')->nullable()->after('email');
        });
    }
};
