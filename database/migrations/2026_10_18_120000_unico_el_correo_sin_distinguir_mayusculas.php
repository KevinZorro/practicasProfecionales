<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * La entrada con Google busca el correo con LOWER(email) (AccesoService),
 * pero el índice único de users.email distingue mayúsculas: dos cuentas que
 * solo difieren en ellas serían posibles y la entrada encontraría la
 * primera (QA-22). Si ya hay duplicados, la migración falla con el nombre de
 * los correos repetidos para que se resuelvan a mano antes de aplicarla.
 */
return new class extends Migration
{
    public function up(): void
    {
        $repetidos = DB::table('users')
            ->selectRaw('LOWER(email) AS correo')
            ->groupByRaw('LOWER(email)')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('correo');

        if ($repetidos->isNotEmpty()) {
            throw new RuntimeException('Hay correos repetidos que solo difieren en mayúsculas: '.$repetidos->implode(', ').'. Unifícalos antes de migrar.');
        }

        DB::statement('CREATE UNIQUE INDEX users_email_minusculas_unique ON users (LOWER(email))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_email_minusculas_unique');
    }
};
