<?php

declare(strict_types=1);

use App\Models\ItemInventario;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * S.1 de la auditoría: la aplicación entra a PostgreSQL con un usuario que
 * solo lee y escribe filas. Con el superusuario, una inyección de SQL o una
 * ejecución remota en el contenedor app sería DROP DATABASE o comandos en el
 * servidor de la base (COPY ... TO PROGRAM). Las tablas las crea el dueño,
 * en el servicio "migraciones", que después corre este comando.
 *
 * El rol se crea dentro de la transacción de la prueba y se deshace con
 * ella; SET ROLE deja comprobar sus permisos sin una segunda conexión.
 */

const USUARIO_DE_PRUEBA = 'laboratorio_app_prueba';

beforeEach(function (): void {
    config(['database.usuario_de_la_aplicacion' => ['usuario' => USUARIO_DE_PRUEBA, 'contrasena' => 'una-clave-de-prueba']]);
});

/** Corre la acción con los permisos del usuario de la aplicación. */
function comoLaAplicacion(Closure $accion): mixed
{
    DB::statement('SET ROLE '.USUARIO_DE_PRUEBA);

    try {
        return $accion();
    } finally {
        DB::statement('RESET ROLE');
    }
}

it('crea el usuario de la aplicación sin superusuario ni permiso para crear bases o roles', function (): void {
    $this->artisan('base:usuario-de-la-aplicacion')->assertSuccessful();

    $rol = DB::selectOne('select rolsuper, rolcreatedb, rolcreaterole, rolcanlogin from pg_roles where rolname = ?', [USUARIO_DE_PRUEBA]);

    expect($rol)->not->toBeNull()
        ->and($rol->rolsuper)->toBeFalse()
        ->and($rol->rolcreatedb)->toBeFalse()
        ->and($rol->rolcreaterole)->toBeFalse()
        ->and($rol->rolcanlogin)->toBeTrue();
});

it('deja a la aplicación leer, crear, cambiar y borrar filas', function (): void {
    $this->artisan('base:usuario-de-la-aplicacion')->assertSuccessful();
    $existente = ItemInventario::factory()->create();

    comoLaAplicacion(function () use ($existente): void {
        $nuevo = ItemInventario::factory()->create();
        $nuevo->update(['descripcion' => 'Cambiada por la aplicación']);
        $existente->delete();

        expect(DB::table('items_inventario')->pluck('id')->all())->toBe([$nuevo->id]);
    });
});

it('no deja a la aplicación crear, cambiar ni borrar tablas', function (string $sentencia): void {
    $this->artisan('base:usuario-de-la-aplicacion')->assertSuccessful();

    comoLaAplicacion(function () use ($sentencia): void {
        expect(fn () => DB::transaction(fn () => DB::statement($sentencia)))->toThrow(QueryException::class);
    });
})->with([
    'crear' => ['CREATE TABLE intrusa (id int)'],
    'cambiar' => ['ALTER TABLE items_inventario ADD COLUMN intrusa int'],
    'borrar' => ['DROP TABLE items_inventario'],
    'vaciar' => ['TRUNCATE items_inventario'],
]);

it('se puede correr en cada despliegue: la segunda vez actualiza y no falla', function (): void {
    $this->artisan('base:usuario-de-la-aplicacion')->assertSuccessful();
    $this->artisan('base:usuario-de-la-aplicacion')->assertSuccessful();

    expect(DB::table('pg_roles')->where('rolname', USUARIO_DE_PRUEBA)->count())->toBe(1);
});

it('no corre sin contraseña, con un nombre raro ni con el mismo usuario del dueño', function (string $usuario, string $contrasena): void {
    config(['database.usuario_de_la_aplicacion' => ['usuario' => $usuario, 'contrasena' => $contrasena]]);

    $this->artisan('base:usuario-de-la-aplicacion')->assertFailed();
})->with([
    'sin contraseña' => [USUARIO_DE_PRUEBA, ''],
    'nombre con comillas' => ['app"; DROP TABLE users; --', 'clave'],
    'el mismo dueño' => [fn (): string => (string) DB::connection()->getConfig('username'), 'clave'],
]);
