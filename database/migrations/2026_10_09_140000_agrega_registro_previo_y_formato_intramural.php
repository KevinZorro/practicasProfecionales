<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sesiones apartadas antes del semestre (RF57) y formato intramural (RF59).
 *
 * "origen" distingue la solicitud que hace el docente de la sesión que un
 * administrativo registra desde el formato físico que le entrega
 * coordinación: esa llega ya aprobada y sin insumos.
 *
 * El formato intramural son los insumos, equipos y simuladores de la
 * sesión. En la solicitud del docente vienen con ella; en la sesión apartada
 * llegan después, en papel, y los digita un administrativo. Las dos columnas
 * dicen si ya está y quién lo registró. Las solicitudes que ya existen lo
 * traían desde que se crearon.
 *
 * ajustes_laboratorio guarda valores que el ADMIN cambia sin desplegar,
 * como la antelación del aviso del RF60. Claves fijas en AjusteDelLaboratorio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes', function (Blueprint $tabla): void {
            $tabla->string('origen')->default('docente');
            $tabla->foreignId('registrada_por')->nullable()->constrained('users')->restrictOnDelete();
            $tabla->timestamp('formato_intramural_at')->nullable();
            $tabla->foreignId('formato_intramural_por')->nullable()->constrained('users')->restrictOnDelete();

            $tabla->index(['origen', 'fecha']);
        });

        DB::table('solicitudes')->update([
            'formato_intramural_at' => DB::raw('created_at'),
            'formato_intramural_por' => DB::raw('docente_id'),
        ]);

        Schema::create('ajustes_laboratorio', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->string('clave')->unique();
            $tabla->string('valor');
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes_laboratorio');

        Schema::table('solicitudes', function (Blueprint $tabla): void {
            $tabla->dropIndex(['origen', 'fecha']);
            $tabla->dropConstrainedForeignId('formato_intramural_por');
            $tabla->dropConstrainedForeignId('registrada_por');
            $tabla->dropColumn(['origen', 'formato_intramural_at']);
        });
    }
};
