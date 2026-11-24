<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La entrada con correo y contraseña (ADR 0011) vuelve a necesitar la
 * columna. Es nullable: una cuenta sin contraseña solo entra con Google, y
 * así nacen todas las que trae la sincronización.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $tabla): void {
            $tabla->string('password')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $tabla): void {
            $tabla->dropColumn('password');
        });
    }
};
