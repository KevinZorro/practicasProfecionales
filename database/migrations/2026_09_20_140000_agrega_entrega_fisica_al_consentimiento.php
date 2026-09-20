<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entrega del formato firmado en físico, en la puerta del laboratorio
 * (RF53).
 *
 * No es un estado más del enum: el estado describe el ciclo del documento
 * escaneado (nada → cargado → verificado) y esto describe un hecho del
 * mundo que convive con cualquiera de esos tres. Un estudiante puede haber
 * entregado el papel y subir el escaneo tres días después, y hay que
 * conservar las dos cosas a la vez, incluido quién recibió el papel.
 *
 * Mismo patrón que verificado_por / verificado_at: el acto queda fechado y
 * con responsable, al lado del estado, no en su lugar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consentimientos_estudiante', function (Blueprint $tabla): void {
            $tabla->timestamp('recibido_fisico_at')->nullable()->after('archivo_firmado_path');
            $tabla->foreignId('recibido_fisico_por')->nullable()->after('recibido_fisico_at')
                ->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('consentimientos_estudiante', function (Blueprint $tabla): void {
            $tabla->dropConstrainedForeignId('recibido_fisico_por');
            $tabla->dropColumn('recibido_fisico_at');
        });
    }
};
