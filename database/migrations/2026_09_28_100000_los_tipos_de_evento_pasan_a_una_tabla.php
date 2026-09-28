<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * El tipo de evento (RF05, RF14) deja de ser una lista fija en el código y
 * pasa a un catálogo que el ADMIN gestiona desde su panel. El RF05 pedía
 * registrar el tipo sin enumerar los valores, y los cuatro del enum nunca
 * se confirmaron con el cliente: ahora los define quien los conoce.
 *
 * Los eventos que ya existan conservan su tipo: cada valor distinto de la
 * columna vieja se convierte en una fila del catálogo antes de borrarla.
 *
 * La llave es restrict: un tipo con eventos no se borra, se desactiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_evento', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->string('nombre')->unique();
            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();
        });

        Schema::table('eventos', function (Blueprint $tabla): void {
            $tabla->foreignId('tipo_evento_id')->nullable()->after('fecha')->constrained('tipos_evento')->restrictOnDelete();
        });

        $ahora = now();

        foreach (DB::table('eventos')->distinct()->orderBy('tipo')->pluck('tipo') as $valor) {
            $id = DB::table('tipos_evento')->insertGetId([
                'nombre' => Str::ucfirst((string) $valor),
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);

            DB::table('eventos')->where('tipo', $valor)->update(['tipo_evento_id' => $id]);
        }

        Schema::table('eventos', function (Blueprint $tabla): void {
            $tabla->foreignId('tipo_evento_id')->nullable(false)->change();
            $tabla->dropColumn('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $tabla): void {
            $tabla->string('tipo')->nullable()->after('fecha');
        });

        foreach (DB::table('tipos_evento')->get(['id', 'nombre']) as $tipo) {
            DB::table('eventos')->where('tipo_evento_id', $tipo->id)->update(['tipo' => Str::lower($tipo->nombre)]);
        }

        Schema::table('eventos', function (Blueprint $tabla): void {
            $tabla->string('tipo')->nullable(false)->change();
            $tabla->dropConstrainedForeignId('tipo_evento_id');
        });

        Schema::dropIfExists('tipos_evento');
    }
};
