<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Sesiones autenticadas para las pruebas de rendimiento
|--------------------------------------------------------------------------
|
| La única entrada es Google (RF18), y una prueba de carga no puede pasar
| por Google ciento cincuenta veces. Este script deja en el almacén de
| sesiones una sesión ya autenticada por cada usuario elegido, con su rol
| activo, y devuelve la cookie cifrada con APP_KEY que el navegador
| mandaría. k6 la pone en su tarro de cookies y entra como esa persona.
|
| Una sesión así es una llave del sistema: el script se niega a correr si
| no se confirma que la base es desechable (staging, la CI o un equipo de
| pruebas con datos generados), nunca la de producción con personas reales.
|
| Arranca Laravel desde el directorio actual, así que corre igual en el
| equipo, desde la raíz del proyecto:
|
|   RENDIMIENTO_BASE_DESECHABLE=si php rendimiento/herramientas/crear-sesiones.php > sesiones.json
|
| y dentro del contenedor de producción, por la entrada estándar, porque
| rendimiento/ no entra en la imagen (.dockerignore):
|
|   docker compose -f docker-compose.produccion.yml exec -T -e RENDIMIENTO_BASE_DESECHABLE=si \
|     app php < rendimiento/herramientas/crear-sesiones.php > sesiones.json
|
| Variables:
|   RENDIMIENTO_BASE_DESECHABLE  "si" para confirmar que la base es de pruebas (obligatoria).
|   RENDIMIENTO_SESIONES_POR_ROL cuántas sesiones crear como mucho por rol (50 por defecto).
|
| Salida (stdout), JSON:
|   {"generado": "...", "cookie": "<nombre de la cookie de sesión>",
|    "sesiones": [{"rol": "docente", "usuario_id": 12, "valor": "<cookie cifrada>"}, ...]}
|
| Las sesiones caducan solas con SESSION_LIFETIME sin actividad; mientras
| una prueba las use, cada petición las renueva.
|
*/

use App\Enums\Rol;
use App\Models\User;
use App\Services\AccesoService;
use App\Support\RolActivo;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

const SESIONES_POR_ROL_POR_DEFECTO = 50;

if (getenv('RENDIMIENTO_BASE_DESECHABLE') !== 'si') {
    fwrite(STDERR, "Este script crea sesiones que entran como usuarios reales.\n"
        ."Solo se corre sobre una base de pruebas: confírmalo con RENDIMIENTO_BASE_DESECHABLE=si.\n");
    exit(1);
}

$raiz = getcwd();

if ($raiz === false || ! is_file($raiz.'/vendor/autoload.php')) {
    fwrite(STDERR, "Corre el script desde la raíz del proyecto (donde está vendor/).\n");
    exit(1);
}

require $raiz.'/vendor/autoload.php';

$aplicacion = require $raiz.'/bootstrap/app.php';
$aplicacion->make(Kernel::class)->bootstrap();

$porRol = (int) (getenv('RENDIMIENTO_SESIONES_POR_ROL') ?: SESIONES_POR_ROL_POR_DEFECTO);
$acceso = $aplicacion->make(AccesoService::class);
$cifrador = $aplicacion->make('encrypter');
$nombreDeLaCookie = (string) config('session.cookie');
$manejador = $aplicacion->make('session')->driver()->getHandler();
$guardia = Auth::guard('web');

$sesiones = [];

foreach (Rol::cases() as $rol) {
    // User::roles() ya filtra por vigencia (regla 13): un rol vencido no se cuela.
    $candidatos = User::query()
        ->whereHas('roles', fn ($consulta) => $consulta->where('name', $rol->value))
        ->orderBy('id')
        ->get()
        ->filter(fn (User $usuario): bool => $acceso->puedeEntrar($usuario))
        ->take($porRol);

    foreach ($candidatos as $usuario) {
        $id = Str::random(40);
        $sesion = new Store($nombreDeLaCookie, $manejador, $id);

        $sesion->put('_token', Str::random(40));
        $sesion->put($guardia->getName(), $usuario->getAuthIdentifier());
        $sesion->put(RolActivo::CLAVE_DE_SESION, $rol->value);

        // Para que la fila de sessions lleve user_id, como la de una entrada real.
        $guardia->setUser($usuario);

        // El manejador de base de datos recuerda si la última sesión que
        // escribió ya existía; sin esto, la segunda se "actualizaría" sin crearse.
        if ($manejador instanceof DatabaseSessionHandler) {
            $manejador->setExists(false);
        }

        $sesion->save();

        $sesiones[] = [
            'rol' => $rol->value,
            'usuario_id' => $usuario->getAuthIdentifier(),
            'valor' => $cifrador->encrypt(CookieValuePrefix::create($nombreDeLaCookie, $cifrador->getKey()).$id, false),
        ];
    }

    fwrite(STDERR, sprintf("%-15s %d sesiones\n", $rol->value, $candidatos->count()));
}

echo json_encode([
    'generado' => now()->toIso8601String(),
    'cookie' => $nombreDeLaCookie,
    'sesiones' => $sesiones,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
