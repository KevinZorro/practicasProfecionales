<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Captura del SQL real de las consultas calientes
|--------------------------------------------------------------------------
|
| Llama a los Services de la aplicación con DB::listen encendido y escribe
| cada pantalla o petición como un script de pgbench en
| rendimiento/base-de-datos/consultas/<nombre>.sql: el SQL que genera
| Laravel de verdad, con los parámetros que cambian por pantalla sustituidos
| por variables aleatorias (\set) dentro de los rangos de la base generada
| con rendimiento/datos/generar-volumen.php.
|
| Dos límites a tener presentes al leer los resultados:
|   - Las consultas de carga anticipada (with()) llevan la lista de ids que
|     salió de la captura, fija: miden el plan, no la variedad de ids.
|   - Las fechas van relativas a CURRENT_DATE (el volumen se genera en torno
|     a "hoy"): si la base se generó otro día, el desfase mueve la ventana.
|
| No escribe en la base: la única escritura (la sesión de la captura) corre
| dentro de una transacción que se deshace.
|
|   DB_HOST=127.0.0.1 DB_DATABASE=laboratorio_rendimiento \
|     php rendimiento/base-de-datos/capturar-consultas.php
|
| Variables:
|   RENDIMIENTO_SALIDA  carpeta de salida (rendimiento/base-de-datos/consultas por defecto).
|
*/

use App\Enums\AccionAuditada;
use App\Enums\EstadoSolicitud;
use App\Models\ItemInventario;
use App\Models\User;
use App\Services\BitacoraService;
use App\Services\CrucesDeSesionService;
use App\Services\FiltroReporte;
use App\Services\Franja;
use App\Services\InventarioService;
use App\Services\ParticipacionService;
use App\Services\ReporteService;
use App\Services\ResumenDelDiaService;
use App\Services\SolicitudService;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

$raiz = getcwd();

if ($raiz === false || ! is_file($raiz.'/vendor/autoload.php')) {
    fwrite(STDERR, "Corre el script desde la raíz del proyecto (donde está vendor/).\n");
    exit(1);
}

require $raiz.'/vendor/autoload.php';

$aplicacion = require $raiz.'/bootstrap/app.php';
$aplicacion->make(Kernel::class)->bootstrap();

$salida = getenv('RENDIMIENTO_SALIDA') ?: $raiz.'/rendimiento/base-de-datos/consultas';

if (! is_dir($salida) && ! mkdir($salida, 0775, true)) {
    fwrite(STDERR, "No se pudo crear $salida\n");
    exit(1);
}

if (DB::table('solicitudes')->count() < 1000) {
    fwrite(STDERR, "La base no tiene volumen: genera antes con rendimiento/datos/generar-volumen.php.\n");
    exit(1);
}

// ---------------------------------------------------------------------
// Utilidades
// ---------------------------------------------------------------------

/** El SQL de lo que haga la función, en orden: [[sql, bindings], ...]. */
function capturar(callable $accion, bool $toleraExcepcion = false): array
{
    $consultas = [];

    DB::listen(static function (QueryExecuted $consulta) use (&$consultas): void {
        $consultas[] = [$consulta->sql, $consulta->bindings];
    });

    try {
        $accion();
    } catch (Throwable $excepcion) {
        // Las comprobaciones de cruce lanzan cuando encuentran uno, pero su consulta ya corrió y es lo que se mide.
        if (! $toleraExcepcion) {
            throw $excepcion;
        }
    }

    // Laravel no permite quitar un listener: este se queda, pero escribe en un arreglo que ya nadie lee.
    return $consultas;
}

function literal(mixed $valor): string
{
    return match (true) {
        $valor === null => 'NULL',
        is_bool($valor) => $valor ? 'true' : 'false',
        is_int($valor), is_float($valor) => (string) $valor,
        default => "'".str_replace("'", "''", (string) $valor)."'",
    };
}

/**
 * Pone los parámetros en el SQL. `$sustituciones` cambia un literal exacto
 * (tal como queda inlinado, p. ej. "'2026-10-22'" o "5123") por una
 * expresión de pgbench: la comparación es del valor completo, no de un
 * trozo del texto.
 *
 * @param  list<mixed>  $parametros
 * @param  array<string, string>  $sustituciones
 */
function inlinar(string $sql, array $parametros, array $sustituciones): string
{
    $indice = 0;

    return (string) preg_replace_callback('/\?/', static function () use ($parametros, &$indice, $sustituciones): string {
        $texto = literal($parametros[$indice++]);

        return $sustituciones[$texto] ?? $texto;
    }, $sql);
}

/**
 * @param  list<array{0: string, 1: list<mixed>}>  $consultas
 * @param  list<string>  $variables  líneas \set
 * @param  array<string, string>  $sustituciones
 * @param  array<string, string>  $textuales  reemplazos sobre el SQL ya inlinado (p. ej. el offset de la paginación)
 */
function escribir(string $carpeta, string $nombre, string $descripcion, string $origen, array $consultas, array $variables, array $sustituciones, array $textuales = []): void
{
    $cuerpo = [];

    foreach ($consultas as [$sql, $parametros]) {
        // Los roles y los permisos en caché son costo de toda petición: ya están en peticion-base y roles-vigentes, y repetirlos inflaría cada pantalla.
        if (! in_array($nombre, ['roles-vigentes', 'peticion-base'], true) && (str_contains($sql, 'inner join "model_has_roles"') || str_contains($sql, 'from "cache"'))) {
            continue;
        }

        $texto = inlinar($sql, $parametros, $sustituciones);
        $texto = strtr($texto, $textuales);
        $cuerpo[] = rtrim($texto, ';').';';
    }

    $contenido = "-- $nombre: $descripcion\n-- Origen: $origen\n-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.\n"
        .implode("\n", array_unique($variables))."\n\n"
        .implode("\n\n", $cuerpo)."\n";

    file_put_contents($carpeta.'/'.$nombre.'.sql', $contenido);
    fwrite(STDERR, sprintf("  %-34s %2d consultas\n", $nombre, count($consultas)));
}

/** @return array{0: int, 1: int} menor y mayor id de los usuarios con ese rol. */
function rango(string $rol): array
{
    $consulta = DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')->where('roles.name', $rol);

    return [(int) $consulta->min('model_id'), (int) $consulta->max('model_id')];
}

function usuarioConRol(string $rol): User
{
    [$minimo, $maximo] = rango($rol);

    return User::query()->findOrFail($minimo + intdiv($maximo - $minimo, 2));
}

$hoy = now()->toDateString();
$hoyLiteral = literal($hoy);
$ayer = "'".now()->subDays(20)->toDateString()."'";
$manana = "'".now()->addDays(10)->toDateString()."'";
$sustitucionHoy = [$hoyLiteral => 'CURRENT_DATE'];
$ventanaDeCalendario = [$ayer => '(CURRENT_DATE + :off)', $manana => '(CURRENT_DATE + :off + 30)'];
$variablesDeVentana = ['\set off random(-440, 10)'];

[$docMin, $docMax] = rango('docente');
[$estMin, $estMax] = rango('estudiante');

fwrite(STDERR, "Capturando en $salida\n");

// ---------------------------------------------------------------------
// Lo que corre en cada petición: usuario, roles vigentes y sesión
// ---------------------------------------------------------------------

// Un docente y un estudiante con sesiones aprobadas en la ventana del calendario: si no, las cargas anticipadas no se ejecutan y no se capturan.
$ventanaDesde = now()->subDays(20)->toDateString();
$ventanaHasta = now()->addDays(10)->toDateString();
$docente = User::query()->findOrFail(DB::table('solicitudes')->where('estado', 'aprobada')->whereBetween('fecha', [$ventanaDesde, $ventanaHasta])->groupBy('docente_id')->orderByRaw('count(*) desc')->value('docente_id'));
$estudiante = User::query()->findOrFail(DB::table('estudiante_solicitud')->join('solicitudes', 'solicitudes.id', '=', 'estudiante_solicitud.solicitud_id')
    ->where('solicitudes.estado', 'aprobada')->whereBetween('solicitudes.fecha', [$ventanaDesde, $ventanaHasta])->groupBy('estudiante_id')->orderByRaw('count(*) desc')->value('estudiante_id'));
$coordinador = User::query()->orderBy('id')->whereHas('roles', fn ($c) => $c->where('name', 'coordinador'))->firstOrFail();
$administrativo = User::query()->orderBy('id')->whereHas('roles', fn ($c) => $c->where('name', 'administrativo'))->firstOrFail();
$supervisor = User::query()->orderBy('id')->whereHas('roles', fn ($c) => $c->where('name', 'supervisor'))->firstOrFail();

// El docente y el estudiante cambian en cada pasada; coordinación, administrativos y supervisor son pocos y se dejan fijos.
$variablesDe = static function (User $u) use ($docMin, $docMax, $estMin, $estMax): array {
    return match (true) {
        $u->id >= $docMin && $u->id <= $docMax => ['\set uid random('.$docMin.', '.$docMax.')'],
        $u->id >= $estMin && $u->id <= $estMax => ['\set uid random('.$estMin.', '.$estMax.')'],
        default => [],
    };
};
$mapaUid = static fn (User $u): array => $variablesDe($u) === [] ? [] : [(string) $u->id => ':uid'];
$uid = $variablesDe($docente);

$consultasUsuario = capturar(fn () => Auth::guard('web')->getProvider()->retrieveById($docente->id));
escribir($salida, 'usuario-por-id', 'El usuario de la sesión: se lee en cada petición autenticada.', 'Illuminate\Auth\SessionGuard::user() (EloquentUserProvider)', $consultasUsuario, $uid, $mapaUid($docente));

$consultasRoles = capturar(fn () => User::query()->findOrFail($docente->id)->load('roles'));
// La primera es la búsqueda del usuario; la de roles es la que cuenta.
$soloRoles = array_values(array_filter($consultasRoles, static fn (array $q): bool => str_contains($q[0], 'model_has_roles')));
escribir($salida, 'roles-vigentes', 'Relación de roles filtrada por vigencia (regla 13): se resuelve en cada can()/hasRole() de la petición.', 'app/Models/User.php:104 User::roles()', $soloRoles, $uid, $mapaUid($docente) + $sustitucionHoy);

// Sesión: se usa el manejador real, dentro de una transacción que se deshace.
$manejador = app('session')->driver()->getHandler();
$cargaUtil = str_repeat('a', 1200);
Carbon::setTestNow(Carbon::parse('2030-01-01 00:00:00'));
DB::beginTransaction();
DB::table('sessions')->insert(['id' => 'SESIONCAPTURA', 'user_id' => $docente->id, 'ip_address' => '10.0.0.1', 'user_agent' => 'rendimiento', 'payload' => base64_encode($cargaUtil), 'last_activity' => time()]);

$consultasLeer = capturar(fn () => $manejador->read('SESIONCAPTURA'));
$consultasEscribir = capturar(function () use ($manejador, $cargaUtil): void {
    $manejador->read('SESIONCAPTURA');
    $manejador->write('SESIONCAPTURA', $cargaUtil);
});
$consultasGc = capturar(fn () => $manejador->gc(7200));
DB::rollBack();
Carbon::setTestNow();

$variablesSesion = ['\set sid random(1, 1000)', ...$uid];
$mapaSesion = ["'SESIONCAPTURA'" => "('rend-' || :sid)", literal(base64_encode($cargaUtil)) => "repeat('a', 1600)", (string) Carbon::parse('2030-01-01 00:00:00')->getTimestamp() => 'extract(epoch from now())::int', (string) $docente->id => ':uid'];
$mapaGc = [(string) (Carbon::parse('2030-01-01 00:00:00')->getTimestamp() - 7200) => '(extract(epoch from now())::int - 7200)'];

escribir($salida, 'sesion-leer', 'Lectura de la sesión (almacén sessions en PostgreSQL), una por petición.', 'Illuminate\Session\DatabaseSessionHandler::read()', $consultasLeer, $variablesSesion, $mapaSesion);
// La lectura que precede a la escritura ya está en sesion-leer: aquí solo el UPDATE.
escribir($salida, 'sesion-escribir', 'Escritura de la sesión, una por petición (UPDATE de la fila, con payload).', 'Illuminate\Session\DatabaseSessionHandler::write()', array_slice($consultasEscribir, 1), $variablesSesion, $mapaSesion, ['"user_id" = NULL' => '"user_id" = :uid']);
escribir($salida, 'sesion-gc', 'Recolección de sesiones vencidas (lotería 2/100 de las peticiones).', 'Illuminate\Session\DatabaseSessionHandler::gc()', $consultasGc, [], $mapaGc);

// Lo fijo de toda petición, junto: modela el costo base de abrir cualquier pantalla del panel.
escribir(
    $salida,
    'peticion-base',
    'Lo que toda petición autenticada del panel hace antes de pintar: leer sesión, usuario y roles, y escribir la sesión.',
    'SessionGuard + User::roles() + DatabaseSessionHandler',
    [...$consultasLeer, ...$consultasUsuario, ...$soloRoles, ...array_slice($consultasEscribir, 1)],
    $variablesSesion,
    $mapaSesion + $sustitucionHoy,
    ['"user_id" = NULL' => '"user_id" = :uid'],
);

// ---------------------------------------------------------------------
// Calendario (RF34) para los tres alcances
// ---------------------------------------------------------------------

$solicitudes = app(SolicitudService::class);
$desde = $ayer;
$sinComillas = static fn (string $literal): string => trim($literal, "'");

foreach ([['calendario-coordinador', $coordinador, 'sin límite: ve todo'], ['calendario-docente', $docente, 'solo las suyas o las que dicta'], ['calendario-estudiante', $estudiante, 'solo donde está en la lista (whereHas)']] as [$nombre, $actor, $alcance]) {
    $capturadas = capturar(fn () => $solicitudes->paraCalendario($sinComillas($desde), $sinComillas($manana), $actor));
    escribir($salida, $nombre, "Calendario de un mes ($alcance).", 'app/Services/SolicitudService.php:593 paraCalendario', $capturadas, [...$variablesDeVentana, ...$variablesDe($actor)], $ventanaDeCalendario + $mapaUid($actor) + $sustitucionHoy);
}

// ---------------------------------------------------------------------
// Bandejas de solicitudes
// ---------------------------------------------------------------------

$pagina = ['offset 0' => 'offset :pagina'];
$variablePagina = ['\set pagina random(0, 40) * 15'];

foreach ([['bandeja-pendientes', EstadoSolicitud::Pendiente], ['bandeja-revisadas', EstadoSolicitud::Revisada]] as [$nombre, $estado]) {
    $capturadas = capturar(fn () => $solicitudes->bandeja($estado));
    escribir($salida, $nombre, 'Bandeja de revisión/aprobación, por estado, con su conteo y la carga anticipada.', 'app/Services/SolicitudService.php:456 bandeja', $capturadas, $variablePagina, $sustitucionHoy, $pagina);
}

$capturadas = capturar(fn () => $solicitudes->vencidas());
escribir($salida, 'bandeja-vencidas', 'Pendientes o revisadas cuya fecha ya pasó (P6).', 'app/Services/SolicitudService.php:474 vencidas', $capturadas, $variablePagina, $sustitucionHoy, $pagina);

$capturadas = capturar(fn () => $solicitudes->historialDelDocente($docente));
escribir($salida, 'historial-docente', 'Mis solicitudes: historial del docente, paginado.', 'app/Services/SolicitudService.php:438 historialDelDocente', $capturadas, [...$variablePagina, ...$uid], $mapaUid($docente) + $sustitucionHoy, $pagina);

// ---------------------------------------------------------------------
// Pantalla «Hoy»
// ---------------------------------------------------------------------

$resumen = app(ResumenDelDiaService::class);

foreach ([['resumen-coordinador', $coordinador], ['resumen-administrativo', $administrativo], ['resumen-docente', $docente], ['resumen-estudiante', $estudiante], ['resumen-supervisor', $supervisor]] as [$nombre, $actor]) {
    $capturadas = capturar(fn () => $resumen->para(User::query()->findOrFail($actor->id), now()));
    escribir($salida, $nombre, 'Pantalla «Hoy» del panel (todas las consultas de un bloque por permiso).', 'app/Services/ResumenDelDiaService.php:46 para()', $capturadas, $variablesDe($actor), $mapaUid($actor) + $sustitucionHoy);
}

// ---------------------------------------------------------------------
// Inventario: disponibilidad por franja y cobertura de lo aprobado
// ---------------------------------------------------------------------

$inventario = app(InventarioService::class);
$idsDeItems = DB::table('caso_clinico_item')->orderBy('caso_clinico_id')->limit(6)->pluck('item_inventario_id')->all();
$franjaFecha = "'".now()->addDays(5)->toDateString()."'";
$mapaFranja = [$franjaFecha => '(CURRENT_DATE + :off)', "'09:00:00'" => "(TIME '07:00' + :h * INTERVAL '1 hour')", "'11:00:00'" => "(TIME '07:00' + (:h + 2) * INTERVAL '1 hour')"];
$variablesFranja = ['\set off random(-440, 40)', '\set h random(0, 10)'];

$capturadas = capturar(fn () => $inventario->disponibilidadDeVarios(ItemInventario::query()->whereIn('id', $idsDeItems)->get(), $sinComillas($franjaFecha), '09:00:00', '11:00:00'));
escribir($salida, 'inventario-disponibilidad', 'Disponibilidad de los ítems de un escenario en una franja (operativas menos comprometidas).', 'app/Services/InventarioService.php:227 disponibilidadDeVarios', $capturadas, $variablesFranja, $mapaFranja);

$capturadas = capturar(fn () => $inventario->solicitudesAprobadasSinCobertura());
escribir($salida, 'inventario-sin-cobertura', 'Aprobadas futuras que se quedaron sin unidades (RF66.2): una consulta con subconsultas.', 'app/Services/InventarioService.php:255 solicitudesAprobadasSinCobertura', $capturadas, [], $sustitucionHoy);

// ---------------------------------------------------------------------
// Cruces de sesión (P9)
// ---------------------------------------------------------------------

$cruces = app(CrucesDeSesionService::class);
$franja = new Franja($sinComillas($franjaFecha), '09:00:00', '11:00:00');
$estudiantesDeLaPrueba = DB::table('estudiante_solicitud')->join('solicitudes', 'solicitudes.id', '=', 'estudiante_solicitud.solicitud_id')
    ->where('solicitudes.estado', 'aprobada')->orderBy('solicitud_id')->limit(15)->pluck('estudiante_id')->map(fn ($id): int => (int) $id)->all();

$capturadas = capturar(fn () => $cruces->garantizarDocenteLibre($docente, $franja), true);
escribir($salida, 'cruces-docente', 'Comprobación de que el docente no dicta otra sesión a la vez (al solicitar, reprogramar o sustituir).', 'app/Services/CrucesDeSesionService.php:44 garantizarDocenteLibre', $capturadas, [...$variablesFranja, ...$uid], $mapaFranja + $mapaUid($docente) + $sustitucionHoy);

$mapaEstudiantes = $mapaFranja;
$variablesEstudiantes = $variablesFranja;

foreach ($estudiantesDeLaPrueba as $i => $id) {
    $mapaEstudiantes[(string) $id] = ':e'.($i + 1);
    $variablesEstudiantes[] = sprintf('\set e%d random(%d, %d)', $i + 1, $estMin, $estMax);
}

$capturadas = capturar(fn () => $cruces->garantizarEstudiantesLibres($estudiantesDeLaPrueba, $franja), true);
escribir($salida, 'cruces-estudiantes', 'Comprobación de que ninguno de 15 estudiantes está en otra sesión que se cruce (al armar o completar la lista).', 'app/Services/CrucesDeSesionService.php:59 garantizarEstudiantesLibres', $capturadas, $variablesEstudiantes, $mapaEstudiantes);

// Impedimentos de ingreso de la lista de una sesión (RF70).
$participacion = app(ParticipacionService::class);
$capturadas = capturar(fn () => $participacion->impedimentos($estudiantesDeLaPrueba));
escribir($salida, 'participacion-lista', 'Quién puede entrar a la práctica en la lista de una sesión: formato al día y sin bloqueo (RF70), 15 personas.', 'app/Services/ParticipacionService.php:48 impedimentos', $capturadas, array_slice($variablesEstudiantes, 2), array_slice($mapaEstudiantes, 3, null, true) + $sustitucionHoy);

// ---------------------------------------------------------------------
// Reportes (RF54, RF55): agregaciones sobre un rango
// ---------------------------------------------------------------------

$reportes = app(ReporteService::class);
$ventanaDeReporte = [$ayer => '(CURRENT_DATE + :off)', $manana => '(CURRENT_DATE + :off + 60)'];
$variablesReporte = ['\set off random(-440, -30)'];
$filtro = new FiltroReporte($sinComillas($ayer), $sinComillas($manana));

$capturadas = capturar(fn () => $reportes->usoDeEscenarios($filtro)->get());
escribir($salida, 'reporte-uso-escenarios', 'Horas de uso por docente, materia y escenario en un rango (agrupación con join lateral).', 'app/Services/ReporteService.php:57 usoDeEscenarios', $capturadas, $variablesReporte, $ventanaDeReporte + $sustitucionHoy);

$capturadas = capturar(fn () => $reportes->paginar($reportes->resultadosDeEvaluacion($filtro)));
escribir($salida, 'reporte-resultados-evaluacion', 'Resultados de evaluaciones finalizadas por estudiante, primera página (RF55).', 'app/Services/ReporteService.php:98 resultadosDeEvaluacion', $capturadas, [...$variablesReporte, ...$variablePagina], $ventanaDeReporte + $sustitucionHoy, $pagina);

// ---------------------------------------------------------------------
// Bitácora paginada (RF62)
// ---------------------------------------------------------------------

$bitacora = app(BitacoraService::class);
$capturadas = capturar(fn () => $bitacora->listado());
escribir($salida, 'bitacora-pagina', 'Bitácora, la más reciente primero, página cualquiera (de 25 en 25).', 'app/Services/BitacoraService.php:77 listado', $capturadas, ['\set pagina random(0, 400) * 25'], [], $pagina);

$capturadas = capturar(fn () => $bitacora->listado(AccionAuditada::SolicitudAprobada, null, $sinComillas($ayer), $sinComillas($manana)));
escribir($salida, 'bitacora-filtrada', 'Bitácora filtrada por acción y rango de fechas.', 'app/Services/BitacoraService.php:77 listado', $capturadas, ['\set off random(-440, 10)', '\set pagina random(0, 10) * 25'], $ventanaDeCalendario, $pagina);

fwrite(STDERR, "Listo.\n");
