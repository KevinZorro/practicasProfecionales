<?php

declare(strict_types=1);

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El documento de identidad se guarda cifrado (S-03 del informe de seguridad).
 *
 * Lo cifra el cast "encrypted" de User con APP_KEY. Tres consecuencias:
 *  - El texto cifrado no cabe en 255 caracteres: la columna pasa a text.
 *  - El índice sobre "documento" deja de servir: el cifrado de Laravel usa un
 *    vector distinto cada vez, así que dos cifrados del mismo número no
 *    coinciden y ninguna consulta SQL puede buscar por él. La sincronización
 *    compara en memoria, ya descifrado.
 *  - Los valores que ya estaban en claro se cifran aquí. Es seguro repetirla:
 *    lo que ya se descifra con la clave actual no se toca.
 *
 * Perder APP_KEY es perder estos documentos: va en la copia de seguridad del
 * .env, aparte de la base (ver "Producción" en el README).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $tabla): void {
            $tabla->dropIndex(['documento']);
        });

        Schema::table('users', function (Blueprint $tabla): void {
            $tabla->text('documento')->nullable()->change();
        });

        DB::table('users')->whereNotNull('documento')->orderBy('id')->each(function (object $cuenta): void {
            if ($this->estaCifrado($cuenta->documento)) {
                return;
            }

            DB::table('users')->where('id', $cuenta->id)->update(['documento' => Crypt::encryptString($cuenta->documento)]);
        });
    }

    public function down(): void
    {
        DB::table('users')->whereNotNull('documento')->orderBy('id')->each(function (object $cuenta): void {
            if (! $this->estaCifrado($cuenta->documento)) {
                return;
            }

            DB::table('users')->where('id', $cuenta->id)->update(['documento' => Crypt::decryptString($cuenta->documento)]);
        });

        Schema::table('users', function (Blueprint $tabla): void {
            $tabla->string('documento')->nullable()->change();
            $tabla->index('documento');
        });
    }

    private function estaCifrado(string $valor): bool
    {
        try {
            Crypt::decryptString($valor);
        } catch (DecryptException) {
            return false;
        }

        return true;
    }
};
