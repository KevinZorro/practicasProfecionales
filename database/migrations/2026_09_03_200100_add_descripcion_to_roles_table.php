<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El modelo relacional pide un campo descriptivo por rol. Los roles los
 * administra spatie/laravel-permission, así que la columna se añade a su
 * tabla en lugar de duplicar el catálogo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $tabla): void {
            $tabla->text('descripcion')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $tabla): void {
            $tabla->dropColumn('descripcion');
        });
    }
};
