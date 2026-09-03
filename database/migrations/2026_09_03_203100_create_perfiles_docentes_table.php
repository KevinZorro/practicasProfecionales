<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perfil público del equipo docente. user_id es nulable porque el perfil
 * puede existir sin cuenta en la plataforma (docente invitado, por ejemplo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perfiles_docentes', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $tabla->string('nombre');
            $tabla->string('cargo');
            $tabla->string('foto')->nullable();
            $tabla->unsignedInteger('orden')->default(0);
            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();

            $tabla->index(['activo', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perfiles_docentes');
    }
};
