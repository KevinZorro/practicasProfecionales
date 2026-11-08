<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\UsuarioDeLaBaseInvalido;
use Illuminate\Support\Facades\DB;

/**
 * El usuario de PostgreSQL con el que entra la aplicación (S.1 de la
 * auditoría): sin superusuario, sin crear bases ni roles, y sin tocar la
 * estructura. Solo lee, crea, cambia y borra filas, y usa las secuencias de
 * los id. Con el superusuario, una inyección de SQL o una ejecución remota
 * en el contenedor app sería DROP DATABASE o comandos en el servidor de la
 * base (COPY ... TO PROGRAM).
 *
 * Lo corre el dueño de la base, en el servicio "migraciones" del compose de
 * producción, después de migrar. Se puede correr en cada despliegue: crea el
 * usuario la primera vez, y después le pone la contraseña del .env y le
 * vuelve a dar los permisos sobre las tablas nuevas.
 */
final class UsuarioDeLaAplicacionService
{
    /** Un nombre de PostgreSQL simple, en minúsculas: se escribe en el SQL sin comillas. */
    private const NOMBRE_VALIDO = '/^[a-z_][a-z0-9_]{0,62}$/';

    public function crearOActualizar(string $usuario, string $contrasena): void
    {
        $this->garantizar($usuario, $contrasena);

        $existe = DB::table('pg_roles')->where('rolname', $usuario)->exists();
        $clave = DB::getPdo()->quote($contrasena);
        $base = '"'.str_replace('"', '""', DB::connection()->getDatabaseName()).'"';

        DB::transaction(static function () use ($usuario, $clave, $base, $existe): void {
            DB::statement(sprintf(
                '%s ROLE %s LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS PASSWORD %s',
                $existe ? 'ALTER' : 'CREATE',
                $usuario,
                $clave,
            ));

            DB::statement("GRANT CONNECT, TEMPORARY ON DATABASE {$base} TO {$usuario}");
            DB::statement("GRANT USAGE ON SCHEMA public TO {$usuario}");
            DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO {$usuario}");
            DB::statement("GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA public TO {$usuario}");

            // Las tablas que creen las migraciones futuras, con los mismos
            // permisos, sin esperar a que se vuelva a correr este comando.
            DB::statement("ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO {$usuario}");
            DB::statement("ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO {$usuario}");
        });
    }

    private function garantizar(string $usuario, string $contrasena): void
    {
        if (preg_match(self::NOMBRE_VALIDO, $usuario) !== 1) {
            throw UsuarioDeLaBaseInvalido::nombreInvalido($usuario);
        }

        if ($contrasena === '') {
            throw UsuarioDeLaBaseInvalido::sinContrasena();
        }

        // Quitarle los permisos al dueño dejaría la base sin quién migre.
        if ($usuario === DB::connection()->getConfig('username')) {
            throw UsuarioDeLaBaseInvalido::esElDueno($usuario);
        }
    }
}
