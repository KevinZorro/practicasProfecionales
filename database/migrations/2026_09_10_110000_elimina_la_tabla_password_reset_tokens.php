<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vestigial por el mismo motivo que users.password: el RF18 fija el ingreso
 * únicamente por cuenta de Google institucional, no hay rutas de
 * recuperación y nada escribe ni lee esta tabla. Dejarla sugiere una
 * autenticación por contraseña que no existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }

    public function down(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $tabla): void {
            $tabla->string('email')->primary();
            $tabla->string('token');
            $tabla->timestamp('created_at')->nullable();
        });
    }
};
