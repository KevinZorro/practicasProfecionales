<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Generador de volumen para las pruebas de rendimiento
|--------------------------------------------------------------------------
|
| Una prueba con 24 estudiantes no dice nada del sistema con 700. Este
| script llena una base recién migrada con el volumen de producción: dos
| periodos académicos cerrados más el vigente (la retención es de dos
| semestres, P13, y el tercero es lo que el ADMIN todavía puede depurar).
|
| Reglas que respeta, porque los planes de consulta dependen de ellas:
|   - nada de ids forzados: los ids salen de las secuencias (RETURNING);
|   - los contadores del inventario cumplen la regla 11 y su historial los
|     reconstruye;
|   - nadie está en dos sesiones a la vez (P9) ni una sala en dos a la vez;
|   - una sesión usa de una a tres salas en las que, juntas, cabe el grupo
|     (regla 4) y no pasa de la capacidad del escenario (regla 10);
|   - los roles con fecha se escriben en model_has_roles con su rastro en
|     asignaciones_de_rol (regla 13); nunca assignRole().
|
| Cifras con RENDIMIENTO_FACTOR=1 (ajustadas a RNF01 y al enunciado):
|   estudiantes 700 activos + 300 egresados inactivos; docentes 150 (3 también
|   supervisores); 8 administrativos (2 pasantes con rol temporal); 2
|   coordinadores (uno también docente) y 1 delegación temporal; 1 admin.
|   1.500 solicitudes por periodo, unas 4.500 en total: 1.500 es lo que
|   ~150 docentes piden en un semestre (10 por cabeza), y es el techo que
|   las 8 salas aguantan de lunes a viernes (12 h al día).
|   ~300 ítems de inventario con historial; 40 escenarios; 8 salas.
| Con el factor se multiplica lo que crece con la población y el tiempo
| (personas, solicitudes y lo que cuelga de ellas: estudiantes por sesión,
| evaluaciones, formatos, bitácora), NO los catálogos fijos (salas,
| escenarios, materias, inventario). Con factor 5 las 8 salas no alcanzan
| para tantas sesiones: las que no encuentran sala libre quedan con su
| preparación pendiente y sin sala, y el resumen final lo dice.
|
| Corre desde la raíz del proyecto, en el equipo:
|
|   RENDIMIENTO_BASE_DESECHABLE=si DB_HOST=127.0.0.1 DB_DATABASE=<base> \
|     php rendimiento/datos/generar-volumen.php
|
| y en el contenedor de producción (rendimiento/ no entra en la imagen),
| por la entrada estándar y con el usuario de la aplicación, que solo lee y
| escribe filas:
|
|   docker compose -f docker-compose.produccion.yml exec -T -e RENDIMIENTO_BASE_DESECHABLE=si \
|     app php < rendimiento/datos/generar-volumen.php
|
| Variables:
|   RENDIMIENTO_BASE_DESECHABLE  "si" para confirmar que la base es de pruebas (obligatoria).
|   RENDIMIENTO_FACTOR           multiplicador de población y tiempo (1 por defecto).
|   RENDIMIENTO_SEMILLA          semilla del generador aleatorio (fija por defecto).
|
*/

use App\Enums\AccionAuditada;
use App\Enums\Rol;
use App\Models\Evaluacion;
use App\Models\FormatoConfidencialidad;
use App\Models\Solicitud;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

const SEMILLA_POR_DEFECTO = 20261111;
const FILAS_POR_LOTE = 1000;
const MAXIMO_DE_USUARIOS_PREVIOS = 50;
const DOMINIO = 'ejemplo.edu.co';

if (getenv('RENDIMIENTO_BASE_DESECHABLE') !== 'si') {
    fwrite(STDERR, "Este script escribe decenas de miles de filas de personas ficticias.\n"
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

ini_set('memory_limit', '3G');

$inicio = microtime(true);
$factor = max(1, (int) (getenv('RENDIMIENTO_FACTOR') ?: 1));
$semilla = (int) (getenv('RENDIMIENTO_SEMILLA') ?: SEMILLA_POR_DEFECTO);
mt_srand($semilla);

$usuariosPrevios = DB::table('users')->count();

if ($usuariosPrevios > MAXIMO_DE_USUARIOS_PREVIOS) {
    fwrite(STDERR, "La base ya tiene $usuariosPrevios usuarios (más de ".MAXIMO_DE_USUARIOS_PREVIOS.'): '
        ."no se genera volumen sobre datos reales. Usa una base recién migrada.\n");
    exit(1);
}

fwrite(STDERR, sprintf("Base: %s, factor %d, semilla %d\n", DB::connection()->getDatabaseName(), $factor, $semilla));

// ---------------------------------------------------------------------
// Utilidades
// ---------------------------------------------------------------------

/** Inserta por lotes sin pedir ids. */
function insertar(string $tabla, array $filas): void
{
    foreach (array_chunk($filas, FILAS_POR_LOTE) as $lote) {
        DB::table($tabla)->insert($lote);
    }
}

/**
 * Inserta por lotes y devuelve los ids en el orden de las filas. Los ids los
 * da la secuencia: el usuario de la aplicación no la administra y no debe
 * forzarla. Todas las filas llevan las mismas columnas.
 *
 * @return list<int>
 */
function insertarConIds(string $tabla, array $filas): array
{
    if ($filas === []) {
        return [];
    }

    $columnas = array_keys($filas[0]);
    $lista = implode(', ', array_map(static fn (string $c): string => '"'.$c.'"', $columnas));
    $marcas = '('.implode(', ', array_fill(0, count($columnas), '?')).')';
    $ids = [];

    foreach (array_chunk($filas, FILAS_POR_LOTE) as $lote) {
        $valores = [];

        foreach ($lote as $fila) {
            foreach ($columnas as $columna) {
                $valores[] = $fila[$columna];
            }
        }

        $sql = sprintf('INSERT INTO "%s" (%s) VALUES %s RETURNING id', $tabla, $lista, implode(', ', array_fill(0, count($lote), $marcas)));

        foreach (DB::select($sql, $valores) as $resultado) {
            $ids[] = (int) $resultado->id;
        }
    }

    return $ids;
}

function azar(int $minimo, int $maximo): int
{
    return mt_rand($minimo, $maximo);
}

/** @param  list<mixed>  $lista */
function elegir(array $lista): mixed
{
    return $lista[mt_rand(0, count($lista) - 1)];
}

function probabilidad(int $porcentaje): bool
{
    return mt_rand(1, 100) <= $porcentaje;
}

function marca(CarbonImmutable $momento): string
{
    return $momento->format('Y-m-d H:i:s');
}

function etapa(string $texto): void
{
    static $anterior = null;
    $ahora = microtime(true);
    fwrite(STDERR, sprintf("  [%6.1fs] %s\n", $ahora - ($anterior ?? $ahora), $texto));
    $anterior = $ahora;
}

// ---------------------------------------------------------------------
// Tiempo: tres periodos, el último abierto y alrededor de hoy
// ---------------------------------------------------------------------

$hoy = CarbonImmutable::now()->startOfDay();
$ahora = CarbonImmutable::now();

/** El vigente corre de 100 días atrás a 40 adelante: hay pasado y futuro. */
$periodos = [];
$inicioVigente = $hoy->subDays(100);

foreach ([2, 1, 0] as $atras) {
    $desde = $inicioVigente->subDays(182 * $atras);
    $hasta = $atras === 0 ? $hoy->addDays(40) : $desde->addDays(181);
    $semestre = $desde->month <= 6 ? 1 : 2;
    $periodos[] = [
        'nombre' => $desde->year.'-'.$semestre,
        'desde' => $desde,
        'hasta' => $hasta,
        'vigente' => $atras === 0,
    ];
}

if (count(array_unique(array_column($periodos, 'nombre'))) !== count($periodos)) {
    fwrite(STDERR, "Los nombres de periodo salieron repetidos; revisa la fecha del equipo.\n");
    exit(1);
}

// ---------------------------------------------------------------------
// Roles (en producción los crea RolSeeder; aquí solo si faltan)
// ---------------------------------------------------------------------

foreach (Rol::cases() as $rol) {
    DB::table('roles')->insertOrIgnore([
        'name' => $rol->value,
        'guard_name' => 'web',
        'descripcion' => $rol->descripcion(),
        'created_at' => marca($ahora),
        'updated_at' => marca($ahora),
    ]);
}

/** @var array<string, int> $rolId */
$rolId = DB::table('roles')->where('guard_name', 'web')->pluck('id', 'name')->map(static fn ($id): int => (int) $id)->all();

// ---------------------------------------------------------------------
// Personas
// ---------------------------------------------------------------------

$programas = array_values((array) config('laboratorio.sincronizacion.programas')) ?: ['Enfermería'];

/**
 * @param  list<array<string, mixed>>  $especificaciones
 * @return list<int>
 */
function crearUsuarios(array $especificaciones, array $programas, CarbonImmutable $ahora): array
{
    $filas = [];

    foreach ($especificaciones as $indice => $e) {
        $filas[] = [
            'nombre' => $e['nombre'],
            'email' => $e['email'],
            'email_verified_at' => marca($ahora),
            'google_id' => null,
            // Cifrado con APP_KEY igual que el cast `encrypted` del modelo.
            'documento' => Crypt::encryptString((string) $e['documento']),
            'codigo_institucional' => $e['codigo'],
            'estado' => $e['estado'] ?? 'activo',
            'origen' => $e['origen'],
            'ultima_sincronizacion' => $e['origen'] === 'manual' ? null : marca($ahora),
            'programa' => $programas[$indice % count($programas)],
            'created_at' => marca($ahora->subDays(400)),
            'updated_at' => marca($ahora),
        ];
    }

    return insertarConIds('users', $filas);
}

$nActivos = 700 * $factor;
$nEgresados = 300 * $factor;
$nDocentes = 150 * $factor;
$nAdministrativos = 8;

$espec = [];

$espec[] = ['nombre' => 'Administrador 1', 'email' => 'admin@'.DOMINIO, 'documento' => 1000000001, 'codigo' => 'A0001', 'origen' => 'manual'];

for ($i = 1; $i <= 2; $i++) {
    $espec[] = ['nombre' => "Coordinador $i", 'email' => "coordinador$i@".DOMINIO, 'documento' => 1000000010 + $i, 'codigo' => sprintf('C%04d', $i), 'origen' => 'contratado'];
}

for ($i = 1; $i <= $nAdministrativos; $i++) {
    // Los dos últimos son pasantes: el sistema no los conoce como administrativos, se les da el rol a mano.
    $espec[] = ['nombre' => "Administrativo $i", 'email' => "administrativo$i@".DOMINIO, 'documento' => 1000000100 + $i, 'codigo' => sprintf('P%04d', $i), 'origen' => $i > $nAdministrativos - 2 ? 'manual' : 'contratado'];
}

for ($i = 1; $i <= $nDocentes; $i++) {
    $espec[] = ['nombre' => sprintf('Docente %03d', $i), 'email' => sprintf('docente%03d@%s', $i, DOMINIO), 'documento' => 1100000000 + $i, 'codigo' => sprintf('D%05d', $i), 'origen' => 'contratado'];
}

for ($i = 1; $i <= $nActivos + $nEgresados; $i++) {
    $espec[] = [
        'nombre' => sprintf('Estudiante %05d', $i),
        'email' => sprintf('estudiante%05d@%s', $i, DOMINIO),
        'documento' => 1200000000 + $i,
        'codigo' => sprintf('1150%06d', $i),
        'origen' => 'matriculado',
        // Los egresados conservan el correo pero ya no están vigentes (regla 8).
        'estado' => $i > $nActivos ? 'inactivo' : 'activo',
    ];
}

DB::transaction(function () use ($espec, $programas, $ahora, &$idsUsuarios): void {
    $idsUsuarios = crearUsuarios($espec, $programas, $ahora);
});

$cursor = 0;
$tomar = static function (int $cantidad) use (&$cursor, &$idsUsuarios): array {
    $parte = array_slice($idsUsuarios, $cursor, $cantidad);
    $cursor += $cantidad;

    return $parte;
};

$adminId = $tomar(1)[0];
$coordinadores = $tomar(2);
$administrativos = $tomar($nAdministrativos);
$docentes = $tomar($nDocentes);
$estudiantesActivos = $tomar($nActivos);
$estudiantesEgresados = $tomar($nEgresados);
$pasantes = array_slice($administrativos, -2);
$administrativosPermanentes = array_slice($administrativos, 0, $nAdministrativos - 2);
$supervisores = array_slice($docentes, 10, 3);
$delegado = $docentes[1];

etapa('personas: '.count($idsUsuarios));

// Algunas cuentas deshabilitadas a mano por el ADMIN (RF22): no entran y la sincronización no las toca.
DB::table('users')->whereIn('id', array_slice($estudiantesActivos, -3))->update([
    'deshabilitado_at' => marca($ahora->subDays(20)),
    'deshabilitado_por' => $adminId,
    'motivo_deshabilitacion' => 'Cuenta deshabilitada para la prueba de rendimiento.',
]);

// ---------------------------------------------------------------------
// Roles de las personas (regla 13)
// ---------------------------------------------------------------------

$bitacora = [];

/** @param  array<string, mixed>  $fila */
$anotar = static function (AccionAuditada $accion, int $usuario, ?string $tipo, ?int $id, string $descripcion, CarbonImmutable $cuando, ?string $motivo = null) use (&$bitacora): void {
    $bitacora[] = [
        'accion' => $accion->value,
        'user_id' => $usuario,
        'auditable_type' => $tipo,
        'auditable_id' => $id,
        'descripcion' => $descripcion,
        'motivo' => $motivo,
        'created_at' => marca($cuando),
    ];
};

$modeloUsuario = User::class;
$pivotes = [];
$asignaciones = [];

$darRol = static function (int $usuario, Rol $rol, ?CarbonImmutable $desde = null, ?CarbonImmutable $hasta = null, ?string $motivo = null) use (&$pivotes, &$asignaciones, $rolId, $modeloUsuario, $adminId, $anotar, $ahora): void {
    $pivotes[] = [
        'role_id' => $rolId[$rol->value],
        'model_type' => $modeloUsuario,
        'model_id' => $usuario,
        'desde' => $desde?->toDateString(),
        'hasta' => $hasta?->toDateString(),
    ];

    // Solo los roles con fecha dejan rastro: los permanentes los reparte la sincronización o el seeder.
    if ($desde !== null || $hasta !== null) {
        $asignaciones[] = [
            'user_id' => $usuario,
            'role_id' => $rolId[$rol->value],
            'desde' => $desde?->toDateString(),
            'hasta' => $hasta?->toDateString(),
            'motivo' => $motivo,
            'asignado_por' => $adminId,
            'revocada_at' => null,
            'revocada_por' => null,
            'created_at' => marca($desde ?? $ahora),
            'updated_at' => marca($desde ?? $ahora),
        ];
        $anotar(AccionAuditada::RolAsignado, $adminId, $modeloUsuario, $usuario, sprintf('Asignó el rol %s hasta el %s.', $rol->value, $hasta?->toDateString() ?? 'sin fin'), $desde ?? $ahora, $motivo);
    }
};

$darRol($adminId, Rol::Admin);

foreach ($coordinadores as $coordinador) {
    $darRol($coordinador, Rol::Coordinador);
}

// La coordinadora 1 es además docente: dos roles a la vez y selector de rol activo.
$docentesConRol = [...$docentes, $coordinadores[0]];
array_map(static fn (int $id) => $darRol($id, Rol::Docente), $docentes);
$darRol($coordinadores[0], Rol::Docente);

foreach ($administrativosPermanentes as $administrativo) {
    $darRol($administrativo, Rol::Administrativo);
}

// Los pasantes se van antes de que acaben las clases (RF63).
foreach ($pasantes as $pasante) {
    $darRol($pasante, Rol::Administrativo, $hoy->subDays(60), $hoy->addDays(30));
}

// Delegación temporal de la facultad de aprobar (RF64): exige motivo.
$darRol($delegado, Rol::Coordinador, $hoy->subDays(5), $hoy->addDays(10), 'Delegación mientras la coordinación está en consejo académico.');

foreach ($supervisores as $supervisor) {
    $darRol($supervisor, Rol::Supervisor);
}

array_map(static fn (int $id) => $darRol($id, Rol::Estudiante), [...$estudiantesActivos, ...$estudiantesEgresados]);

DB::transaction(function () use ($pivotes, $asignaciones, $supervisores, $docentes, $adminId, $ahora): void {
    insertar('model_has_roles', $pivotes);
    insertar('asignaciones_de_rol', $asignaciones);

    $aCargo = [];

    foreach ($supervisores as $posicion => $supervisor) {
        // Cada supervisor, 12 docentes distintos de los que no son supervisores.
        foreach (array_slice($docentes, 20 + $posicion * 12, 12) as $docente) {
            $aCargo[] = ['supervisor_id' => $supervisor, 'docente_id' => $docente, 'asignado_por' => $adminId, 'created_at' => marca($ahora), 'updated_at' => marca($ahora)];
        }
    }

    insertar('docente_supervisor', $aCargo);
});

etapa('roles: '.count($pivotes).' filas en model_has_roles');

// ---------------------------------------------------------------------
// Catálogos fijos: periodos, salas, materias, escenarios, evaluaciones, inventario
// ---------------------------------------------------------------------

DB::transaction(function () use ($periodos, $adminId, $ahora, $anotar): void {
    foreach ($periodos as $indice => $periodo) {
        $id = DB::table('periodos_academicos')->insertGetId([
            'nombre' => $periodo['nombre'],
            'abierto_at' => marca($periodo['desde']),
            'abierto_por' => $adminId,
            'cerrado_at' => $periodo['vigente'] ? null : marca($periodo['hasta']->addDay()),
            'cerrado_por' => $periodo['vigente'] ? null : $adminId,
            'created_at' => marca($periodo['desde']),
            'updated_at' => marca($periodo['vigente'] ? $ahora : $periodo['hasta']),
        ]);
        $anotar(AccionAuditada::PeriodoAbierto, $adminId, 'App\Models\PeriodoAcademico', $id, 'Abrió el periodo académico '.$periodo['nombre'].'.', $periodo['desde']);

        if (! $periodo['vigente']) {
            $anotar(AccionAuditada::PeriodoCerrado, $adminId, 'App\Models\PeriodoAcademico', $id, 'Cerró el periodo académico '.$periodo['nombre'].'.', $periodo['hasta']->addDay());
        }
    }
});

// Ocho salas: bloque, piso y número únicos; capacidades que, sumadas de a tres, cubren 20 personas.
$capacidadesDeSalas = [10, 10, 12, 12, 14, 14, 16, 20];
$filasSalas = [];

foreach ($capacidadesDeSalas as $i => $capacidad) {
    $filasSalas[] = [
        'nombre' => 'Sala de simulación '.($i + 1),
        'codigo' => sprintf('SIM-%02d', $i + 1),
        'capacidad' => $capacidad,
        'activo' => true,
        'bloque' => 'Bloque '.chr(65 + intdiv($i, 4)),
        'piso' => (string) (($i % 2) + 1),
        'numero' => (string) (100 + $i),
        'created_at' => marca($ahora->subDays(400)),
        'updated_at' => marca($ahora->subDays(400)),
    ];
}

$salas = DB::transaction(function () use ($filasSalas, $adminId, $ahora): array {
    $ids = insertarConIds('salas', $filasSalas);
    $ubicaciones = [];

    foreach ($ids as $i => $id) {
        $ubicaciones[] = ['sala_id' => $id, 'bloque' => $filasSalas[$i]['bloque'], 'piso' => $filasSalas[$i]['piso'], 'numero' => $filasSalas[$i]['numero'], 'registrada_por' => $adminId, 'created_at' => marca($ahora->subDays(400))];
    }

    insertar('ubicaciones_sala', $ubicaciones);

    return $ids;
});

$capacidadDeSala = array_combine($salas, $capacidadesDeSalas);

$materias = DB::transaction(function () use ($ahora): array {
    $filas = [];

    for ($i = 1; $i <= 12; $i++) {
        $filas[] = ['codigo' => sprintf('MAT-%03d', $i), 'nombre' => "Materia clínica $i", 'semestre' => (($i - 1) % 8) + 2, 'activo' => true, 'created_at' => marca($ahora->subDays(400)), 'updated_at' => marca($ahora->subDays(400))];
    }

    return insertarConIds('materias', $filas);
});

$capacidadesClinicas = DB::transaction(function () use ($ahora): array {
    $filas = [];

    foreach (['Sangrado', 'Llanto', 'Signos vitales', 'Auscultación', 'Pulso periférico', 'Vía aérea', 'Convulsión', 'Contracciones'] as $nombre) {
        $filas[] = ['nombre' => $nombre, 'icono' => null, 'created_at' => marca($ahora), 'updated_at' => marca($ahora)];
    }

    return insertarConIds('capacidades', $filas);
});

// Ocho tipos de evaluación con su checklist (la plantilla que se copia, regla 3), ligados a las materias.
$tiposEvaluacion = DB::transaction(function () use ($materias, $ahora): array {
    $tipos = [];
    $filas = [];

    for ($i = 1; $i <= 8; $i++) {
        $filas[] = ['nombre' => "Evaluación de habilidad $i", 'descripcion' => "Checklist institucional $i.", 'activo' => true, 'created_at' => marca($ahora), 'updated_at' => marca($ahora)];
    }

    $ids = insertarConIds('tipos_evaluacion', $filas);
    $items = [];
    $vinculos = [];

    foreach ($ids as $posicion => $id) {
        $cantidadDeItems = 5 + ($posicion % 4);
        $tipos[$id] = $cantidadDeItems;

        for ($orden = 1; $orden <= $cantidadDeItems; $orden++) {
            $items[] = ['tipo_evaluacion_id' => $id, 'descripcion' => "Paso $orden de la evaluación de habilidad ".($posicion + 1), 'orden' => $orden, 'created_at' => marca($ahora), 'updated_at' => marca($ahora)];
        }

        foreach ([$materias[$posicion], $materias[($posicion + 4) % count($materias)]] as $materia) {
            $vinculos[] = ['materia_id' => $materia, 'tipo_evaluacion_id' => $id];
        }
    }

    insertar('items_checklist', $items);
    insertar('materia_tipo_evaluacion', $vinculos);

    return $tipos;
});

// Inventario: 40 simuladores, 60 accesorios (cada uno de un simulador), 100 equipos clínicos y 100 básicos.
$inventario = DB::transaction(function () use ($adminId, $coordinadores, $administrativosPermanentes, $ahora, $anotar): array {
    $historial = [];
    $alta = $ahora->subDays(380);

    $registrar = static function (int $item, ?string $anterior, string $nuevo, int $cantidad, string $motivo, int $quien, CarbonImmutable $cuando) use (&$historial): void {
        $historial[] = ['item_inventario_id' => $item, 'estado_anterior' => $anterior, 'estado_nuevo' => $nuevo, 'cantidad' => $cantidad, 'motivo' => $motivo, 'registrado_por' => $quien, 'created_at' => marca($cuando), 'updated_at' => marca($cuando)];
    };

    $armar = static function (string $nombre, string $tipo, ?string $nivel, int $total, ?int $simulador) use ($alta): array {
        return ['nombre' => $nombre, 'tipo' => $tipo, 'nivel_fidelidad' => $nivel, 'descripcion' => null, 'activo' => true, 'simulador_id' => $simulador,
            'cantidad_total' => $total, 'cantidad_operativa' => $total, 'cantidad_en_revision' => 0, 'cantidad_defectuosa' => 0,
            'created_at' => marca($alta), 'updated_at' => marca($alta)];
    };

    $filas = [];

    for ($i = 1; $i <= 40; $i++) {
        $filas[] = $armar("Simulador $i", 'simulador', elegir(['baja', 'media', 'alta']), azar(1, 4), null);
    }

    $idsSimuladores = insertarConIds('items_inventario', $filas);

    $filas = [];

    for ($i = 1; $i <= 60; $i++) {
        $filas[] = $armar("Accesorio $i", 'accesorio', null, azar(1, 6), $idsSimuladores[($i - 1) % 40]);
    }

    for ($i = 1; $i <= 100; $i++) {
        $filas[] = $armar("Equipo clínico $i", 'equipo_clinico', null, azar(2, 20), null);
    }

    for ($i = 1; $i <= 100; $i++) {
        $filas[] = $armar("Insumo básico $i", 'equipo_basico', null, azar(50, 500), null);
    }

    $idsResto = insertarConIds('items_inventario', $filas);
    $todos = [...$idsSimuladores, ...$idsResto];

    // Contadores y historial: cada movimiento se anota y se aplica a la vez, así que el historial reconstruye el saldo.
    $saldos = [];
    $leer = DB::table('items_inventario')->select('id', 'cantidad_total')->whereIn('id', $todos)->get();

    foreach ($leer as $fila) {
        $id = (int) $fila->id;
        $total = (int) $fila->cantidad_total;
        $registrar($id, null, 'operativo', $total, 'Alta en el inventario.', $adminId, $alta);
        $operativas = $total;
        $revision = 0;
        $defectuosas = 0;
        $baja = 0;
        $cuando = $alta->addDays(azar(10, 100));

        if ($total >= 2 && probabilidad(35)) {
            $r = azar(1, max(1, intdiv($total, 3)));
            $registrar($id, 'operativo', 'en_revision', $r, 'Revisión por desgaste.', elegir($administrativosPermanentes), $cuando);
            $operativas -= $r;
            $revision += $r;
            $cuando = $cuando->addDays(azar(3, 40));

            if (probabilidad(50)) {
                $v = azar(1, $r);
                $registrar($id, 'en_revision', 'operativo', $v, 'Falsa alarma.', elegir($administrativosPermanentes), $cuando);
                $revision -= $v;
                $operativas += $v;
            } elseif (probabilidad(60)) {
                $d = azar(1, $r);
                $registrar($id, 'en_revision', 'defectuoso', $d, 'No tiene reparación.', elegir($administrativosPermanentes), $cuando);
                $revision -= $d;
                $defectuosas += $d;
                $cuando = $cuando->addDays(azar(3, 40));

                if (probabilidad(50)) {
                    $registrar($id, 'defectuoso', 'dado_de_baja', $d, 'Sin repuesto.', elegir($coordinadores), $cuando);
                    $defectuosas -= $d;
                    $baja += $d;
                    $anotar(AccionAuditada::UnidadesDadasDeBaja, $coordinadores[0], 'App\Models\ItemInventario', $id, 'Dio de baja unidades defectuosas.', $cuando, 'Sin repuesto.');
                }
            }
        }

        if (probabilidad(10) && $operativas >= 2) {
            $registrar($id, 'operativo', 'dado_de_baja', 1, 'Gasto de la práctica.', elegir($administrativosPermanentes), $cuando->addDay());
            $operativas -= 1;
            $baja += 1;
            $anotar(AccionAuditada::UnidadesRetiradas, $administrativosPermanentes[0], 'App\Models\ItemInventario', $id, 'Retiró unidades operativas.', $cuando->addDay(), 'Gasto de la práctica.');
        }

        if (probabilidad(10)) {
            $registrar($id, null, 'operativo', 2, 'Llegaron de la compra.', elegir($administrativosPermanentes), $cuando->addDays(2));
            $operativas += 2;
            $total += 2;
        }

        $total -= $baja;
        $saldos[] = ['id' => $id, 'total' => $total, 'op' => $operativas, 'rev' => $revision, 'def' => $defectuosas];
    }

    foreach ($saldos as $s) {
        DB::table('items_inventario')->where('id', $s['id'])->update([
            'cantidad_total' => $s['total'], 'cantidad_operativa' => $s['op'], 'cantidad_en_revision' => $s['rev'], 'cantidad_defectuosa' => $s['def'],
            'activo' => $s['total'] > 0,
        ]);
    }

    insertar('cambios_estado_item', $historial);

    return $todos;
});

// Cuarenta escenarios con su capacidad máxima (RF74), materias, salas preferidas, capacidades clínicas e ítems.
$casos = DB::transaction(function () use ($materias, $salas, $capacidadesClinicas, $inventario, $ahora): array {
    $filas = [];

    for ($i = 1; $i <= 40; $i++) {
        $filas[] = ['nombre' => "Escenario clínico $i", 'descripcion' => "Descripción del escenario $i.", 'imagen' => null, 'video' => null,
            'visible_publico' => $i <= 12, 'orden' => $i, 'activo' => true,
            // El 10 % queda "sin definir": no limita (regla 10).
            'capacidad_maxima_estudiantes' => $i % 10 === 0 ? null : azar(10, 20),
            'created_at' => marca($ahora->subDays(300)), 'updated_at' => marca($ahora->subDays(300))];
    }

    $ids = insertarConIds('casos_clinicos', $filas);
    $vinculosMateria = $vinculosSala = $vinculosCapacidad = $vinculosItem = [];

    foreach ($ids as $posicion => $id) {
        $vinculosMateria[] = ['caso_clinico_id' => $id, 'materia_id' => $materias[$posicion % count($materias)]];
        $vinculosMateria[] = ['caso_clinico_id' => $id, 'materia_id' => $materias[($posicion + 5) % count($materias)]];
        $vinculosSala[] = ['caso_clinico_id' => $id, 'sala_id' => $salas[$posicion % count($salas)]];

        foreach ([0, 3] as $salto) {
            $vinculosCapacidad[] = ['caso_clinico_id' => $id, 'capacidad_id' => $capacidadesClinicas[($posicion + $salto) % count($capacidadesClinicas)]];
        }

        foreach (array_unique(array_map(static fn (int $k): int => $inventario[($posicion * 7 + $k * 31) % count($inventario)], range(1, azar(3, 6)))) as $item) {
            $vinculosItem[] = ['caso_clinico_id' => $id, 'item_inventario_id' => $item, 'cantidad' => azar(1, 3)];
        }
    }

    insertar('caso_clinico_materia', $vinculosMateria);
    insertar('caso_clinico_sala', $vinculosSala);
    insertar('caso_clinico_capacidad', $vinculosCapacidad);
    insertar('caso_clinico_item', $vinculosItem);

    return $ids;
});

etapa('catálogos: '.count($inventario).' ítems, '.count($casos).' escenarios, '.count($salas).' salas');

// Lo que cada escenario trae de inventario y sus topes, para precargar solicitudes.
$capacidadDelCaso = DB::table('casos_clinicos')->whereIn('id', $casos)->pluck('capacidad_maxima_estudiantes', 'id')->map(static fn ($c): ?int => $c === null ? null : (int) $c)->all();
$itemsDelCaso = [];

foreach (DB::table('caso_clinico_item')->get() as $fila) {
    $itemsDelCaso[(int) $fila->caso_clinico_id][(int) $fila->item_inventario_id] = (int) $fila->cantidad;
}

$materiasDelCaso = [];

foreach (DB::table('caso_clinico_materia')->get() as $fila) {
    $materiasDelCaso[(int) $fila->caso_clinico_id][] = (int) $fila->materia_id;
}

$tiposDeLaMateria = [];

foreach (DB::table('materia_tipo_evaluacion')->get() as $fila) {
    $tiposDeLaMateria[(int) $fila->materia_id][] = (int) $fila->tipo_evaluacion_id;
}

$plantillaId = DB::table('plantillas_confidencialidad')->insertGetId([
    'nombre' => 'Formato de confidencialidad y autorización de captación de imágenes',
    'archivo_path' => 'confidencialidad/plantillas/formato-rendimiento-v1.pdf',
    'version' => '1',
    'activo' => true,
    'subido_por' => $adminId,
    'created_at' => marca($ahora->subDays(300)),
    'updated_at' => marca($ahora->subDays(300)),
]);

// ---------------------------------------------------------------------
// Solicitudes, preparaciones, evaluaciones y formatos, periodo por periodo
// ---------------------------------------------------------------------

/** Ocupación en memoria: con un entero por (persona o sala, día, hora) se comprueba el cruce sin consultar la base. */
$ocupadas = [];
$indiceDeDia = static fn (CarbonImmutable $dia): int => (int) $hoy->diffInDays($dia, false) + 2000;
$clave = static fn (int $dia, int $hora, int $quien, int $tipo): int => ((($dia * 24 + $hora) * 1000000 + $quien) * 4) + $tipo;

$intentosPrevios = [];
$resumen = ['solicitudes' => 0, 'sin_sala' => 0, 'descartadas' => 0];
$formatosFirmantes = [];

foreach ($periodos as $periodo) {
    $vigente = $periodo['vigente'];
    $nombrePeriodo = $periodo['nombre'];

    // Quién pisa el laboratorio: en los periodos cerrados también los hoy egresados.
    $poolEstudiantes = $vigente ? array_slice($estudiantesActivos, 0, $nActivos - 3) : [...$estudiantesActivos, ...$estudiantesEgresados];
    $poolEstudiantes = array_values($poolEstudiantes);
    $totalEstudiantes = count($poolEstudiantes);

    $dias = [];

    for ($d = $periodo['desde']->addDays(7); $d <= $periodo['hasta']->subDays(7); $d = $d->addDay()) {
        if ($d->isWeekday()) {
            $dias[] = $d;
        }
    }

    $especificaciones = [];
    $objetivo = 1500 * $factor;

    for ($n = 0; $n < $objetivo; $n++) {
        $aceptada = false;

        for ($intento = 0; $intento < 40 && ! $aceptada; $intento++) {
            $dia = elegir($dias);
            $pasada = $dia < $hoy;
            $duracion = elegir([1, 2, 2, 3]);
            $horaInicio = azar(7, 19 - $duracion);
            $diaIndice = $indiceDeDia($dia);

            if ($pasada) {
                $estado = elegir([...array_fill(0, 90, 'aprobada'), ...array_fill(0, 6, 'rechazada'), 'pendiente', 'pendiente', 'revisada', 'revisada']);
            } else {
                $estado = elegir([...array_fill(0, 25, 'pendiente'), ...array_fill(0, 25, 'revisada'), ...array_fill(0, 50, 'aprobada')]);
            }

            $tipo = probabilidad(30) ? 'evaluacion' : 'practica';
            $casoId = elegir($casos);
            $materiaId = elegir($materiasDelCaso[$casoId]);
            $docenteId = elegir($docentesConRol);
            $tope = $capacidadDelCaso[$casoId] ?? 20;
            $cantidad = azar(8, max(8, min(20, $tope)));
            $cuentaParaCruces = $estado !== 'rechazada';

            $elegidos = [];

            if ($cuentaParaCruces) {
                $libre = true;

                for ($h = $horaInicio; $h < $horaInicio + $duracion; $h++) {
                    if (isset($ocupadas[$clave($diaIndice, $h, $docenteId, 0)])) {
                        $libre = false;
                        break;
                    }
                }

                if (! $libre) {
                    continue;
                }

                $vistos = [];

                for ($tentativa = 0; count($elegidos) < $cantidad && $tentativa < $cantidad * 12; $tentativa++) {
                    $candidato = $poolEstudiantes[azar(0, $totalEstudiantes - 1)];

                    if (isset($vistos[$candidato])) {
                        continue;
                    }

                    $vistos[$candidato] = true;
                    $ocupado = false;

                    for ($h = $horaInicio; $h < $horaInicio + $duracion; $h++) {
                        if (isset($ocupadas[$clave($diaIndice, $h, $candidato, 1)])) {
                            $ocupado = true;
                            break;
                        }
                    }

                    if (! $ocupado) {
                        $elegidos[] = $candidato;
                    }
                }

                if (count($elegidos) < $cantidad) {
                    continue;
                }
            } else {
                for ($k = 0; $k < $cantidad; $k++) {
                    $elegidos[] = $poolEstudiantes[azar(0, $totalEstudiantes - 1)];
                }

                $elegidos = array_values(array_unique($elegidos));
                $cantidad = count($elegidos);
            }

            // Las salas se piden a las aprobadas que ya pasaron o están a una semana: el resto las asigna el administrativo después.
            $salasElegidas = [];
            $necesitaSalas = $estado === 'aprobada' && $dia <= $hoy->addDays(7);

            if ($necesitaSalas) {
                $orden = $salas;
                shuffle($orden);
                $suma = 0;

                foreach ($orden as $sala) {
                    $libre = true;

                    for ($h = $horaInicio; $h < $horaInicio + $duracion; $h++) {
                        if (isset($ocupadas[$clave($diaIndice, $h, $sala, 2)])) {
                            $libre = false;
                            break;
                        }
                    }

                    if ($libre) {
                        $salasElegidas[] = $sala;
                        $suma += $capacidadDeSala[$sala];

                        if ($suma >= $cantidad || count($salasElegidas) === 3) {
                            break;
                        }
                    }
                }

                if ($suma < $cantidad) {
                    // Sin salas que sumen: se busca otro hueco, y tras quince intentos se acepta sin sala.
                    $salasElegidas = [];

                    if ($intento < 15) {
                        continue;
                    }
                }
            }

            if ($cuentaParaCruces) {
                for ($h = $horaInicio; $h < $horaInicio + $duracion; $h++) {
                    $ocupadas[$clave($diaIndice, $h, $docenteId, 0)] = true;

                    foreach ($elegidos as $estudiante) {
                        $ocupadas[$clave($diaIndice, $h, $estudiante, 1)] = true;
                    }

                    foreach ($salasElegidas as $sala) {
                        $ocupadas[$clave($diaIndice, $h, $sala, 2)] = true;
                    }
                }
            }

            if ($necesitaSalas && $salasElegidas === []) {
                $resumen['sin_sala']++;
            }

            $especificaciones[] = compact('dia', 'horaInicio', 'duracion', 'estado', 'tipo', 'casoId', 'materiaId', 'docenteId', 'elegidos', 'salasElegidas', 'necesitaSalas');
            $aceptada = true;
        }

        if (! $aceptada) {
            $resumen['descartadas']++;
        }
    }

    usort($especificaciones, static fn (array $a, array $b): int => [$a['dia'], $a['horaInicio']] <=> [$b['dia'], $b['horaInicio']]);

    DB::transaction(function () use (
        $especificaciones, $hoy, $ahora, $coordinadores, $administrativos, $itemsDelCaso, $tiposDeLaMateria, $tiposEvaluacion, $anotar, &$intentosPrevios, &$resumen
    ): void {
        $filas = [];
        $cantidadActiva = [];

        foreach ($especificaciones as $s) {
            $dia = $s['dia'];
            $creada = $dia->subDays(azar(3, 20))->setTime(azar(7, 18), azar(0, 59));

            if ($creada > $ahora) {
                $creada = $ahora->subHours(azar(1, 72));
            }

            $retirados = (count($s['elegidos']) > 10 && probabilidad(5)) ? 1 : 0;
            $cantidadActiva[] = count($s['elegidos']) - $retirados;

            // Lo que se resolvió "ayer" no puede tener una hora de mañana.
            $hasta = static fn (CarbonImmutable $momento): string => marca($momento > $ahora ? $ahora : $momento);
            $revisadaPor = $revisadaAt = $resueltaPor = $resueltaAt = $motivo = $observaciones = null;

            switch ($s['estado']) {
                case 'revisada':
                case 'aprobada':
                    $revisadaPor = elegir($administrativos);
                    $revisadaAt = $hasta($creada->addHours(azar(2, 30)));

                    if ($s['estado'] === 'aprobada') {
                        $resueltaPor = elegir($coordinadores);
                        $resueltaAt = $hasta($creada->addHours(azar(31, 80)));
                        $observaciones = probabilidad(3) ? 'Se aprueba con faltantes de inventario; se reponen antes de la sesión.' : null;
                    }

                    break;
                case 'rechazada':
                    // Primera fase: quien revisa rechaza y revisada_por queda nulo; segunda: coordinación.
                    if (probabilidad(50)) {
                        $resueltaPor = elegir($administrativos);
                    } else {
                        $revisadaPor = elegir($administrativos);
                        $revisadaAt = $hasta($creada->addHours(azar(2, 30)));
                        $resueltaPor = elegir($coordinadores);
                    }

                    $resueltaAt = $hasta($creada->addHours(azar(31, 80)));
                    $motivo = 'No hay cupo en el horario pedido.';

                    break;
            }

            $filas[] = [
                'docente_id' => $s['docenteId'],
                'materia_id' => $s['materiaId'],
                'caso_clinico_id' => $s['casoId'],
                'tipo' => $s['tipo'],
                'fecha' => $dia->toDateString(),
                'hora_inicio' => sprintf('%02d:00:00', $s['horaInicio']),
                'hora_fin' => sprintf('%02d:00:00', $s['horaInicio'] + $s['duracion']),
                'cantidad_estudiantes' => $cantidadActiva[count($cantidadActiva) - 1],
                'estado' => $s['estado'],
                'revisada_por' => $revisadaPor,
                'revisada_at' => $revisadaAt,
                'resuelta_por' => $resueltaPor,
                'resuelta_at' => $resueltaAt,
                'motivo_rechazo' => $motivo,
                'observaciones' => null,
                'created_at' => marca($creada),
                'updated_at' => marca($creada->addHours(40)),
                'grupo' => probabilidad(70) ? elegir(['A', 'B', 'C']) : null,
                'origen' => 'docente',
                'registrada_por' => null,
                'formato_intramural_at' => null,
                'formato_intramural_por' => null,
                'docente_que_dicta_id' => null,
                'observaciones_aprobacion' => $observaciones,
            ];
        }

        $ids = insertarConIds('solicitudes', $filas);
        $resumen['solicitudes'] += count($ids);

        $estudiantes = $items = $preparaciones = [];

        foreach ($especificaciones as $i => $s) {
            $solicitud = $ids[$i];
            $retirados = count($s['elegidos']) - $cantidadActiva[$i];

            foreach ($s['elegidos'] as $posicion => $estudiante) {
                $retirado = $posicion < $retirados;
                $estudiantes[] = [
                    'solicitud_id' => $solicitud, 'estudiante_id' => $estudiante,
                    'created_at' => $filas[$i]['created_at'], 'updated_at' => $filas[$i]['created_at'],
                    'retirado_at' => $retirado ? $filas[$i]['updated_at'] : null,
                    'retirado_por' => $retirado ? $s['docenteId'] : null,
                    'motivo_retiro' => $retirado ? 'No asistirá a la sesión.' : null,
                ];
            }

            foreach ($itemsDelCaso[$s['casoId']] ?? [] as $item => $cantidad) {
                $items[] = ['solicitud_id' => $solicitud, 'item_inventario_id' => $item, 'cantidad' => $cantidad];
            }

            if ($s['estado'] === 'aprobada') {
                $pasada = $s['dia'] < $hoy;
                $preparada = $pasada && ($s['salasElegidas'] !== []);
                $preparaciones[] = [
                    'indice' => $i,
                    'fila' => [
                        'solicitud_id' => $solicitud,
                        'estado' => $preparada ? 'preparado' : ($s['salasElegidas'] !== [] ? 'en_preparacion' : 'pendiente'),
                        'preparado_por' => $preparada ? elegir($administrativos) : null,
                        'preparado_at' => $preparada ? marca($s['dia']->subDay()->setTime(17, 0)) : null,
                        'observaciones' => null,
                        'created_at' => $filas[$i]['resuelta_at'],
                        'updated_at' => $filas[$i]['resuelta_at'],
                    ],
                ];
            }

            if ($s['estado'] === 'aprobada' || $s['estado'] === 'rechazada') {
                $accion = $s['estado'] === 'aprobada' ? AccionAuditada::SolicitudAprobada : AccionAuditada::SolicitudRechazada;
                $anotar($accion, (int) $filas[$i]['resuelta_por'], Solicitud::class, $solicitud, ($s['estado'] === 'aprobada' ? 'Aprobó' : 'Rechazó').' la solicitud '.$solicitud.'.', CarbonImmutable::parse($filas[$i]['resuelta_at']), $filas[$i]['motivo_rechazo']);
            }
        }

        insertar('estudiante_solicitud', $estudiantes);
        insertar('solicitud_item', $items);

        $idsPreparaciones = insertarConIds('preparaciones', array_column($preparaciones, 'fila'));
        $salasDePreparacion = $itemsDePreparacion = [];

        foreach ($preparaciones as $posicion => $p) {
            $s = $especificaciones[$p['indice']];
            $preparacion = $idsPreparaciones[$posicion];

            foreach ($s['salasElegidas'] as $sala) {
                $salasDePreparacion[] = ['preparacion_id' => $preparacion, 'sala_id' => $sala];
            }

            foreach ($itemsDelCaso[$s['casoId']] ?? [] as $item => $cantidad) {
                $itemsDePreparacion[] = ['preparacion_id' => $preparacion, 'item_inventario_id' => $item, 'cantidad' => $cantidad, 'alistado' => $p['fila']['estado'] === 'preparado'];
            }
        }

        insertar('preparacion_sala', $salasDePreparacion);
        insertar('preparacion_item', $itemsDePreparacion);

        // Evaluaciones: solo de las aprobadas de tipo evaluación que ya pasaron (regla 1).
        $evaluaciones = [];

        foreach ($especificaciones as $i => $s) {
            if ($s['estado'] === 'aprobada' && $s['tipo'] === 'evaluacion' && $s['dia'] < $hoy) {
                $tipoId = elegir($tiposDeLaMateria[$s['materiaId']] ?? array_keys($tiposEvaluacion));
                $evaluaciones[] = ['indice' => $i, 'tipo' => $tipoId, 'fila' => [
                    'solicitud_id' => $ids[$i],
                    'tipo_evaluacion_id' => $tipoId,
                    'docente_id' => $s['docenteId'],
                    'estado' => $s['dia'] < $hoy->subDays(1) ? 'finalizada' : 'borrador',
                    'created_at' => marca($s['dia']->setTime($s['horaInicio'], 0)),
                    'updated_at' => marca($s['dia']->setTime($s['horaInicio'] + $s['duracion'], 0)),
                ]];
            }
        }

        $idsEvaluaciones = insertarConIds('evaluaciones', array_column($evaluaciones, 'fila'));

        // Los ítems se copian de la plantilla a la evaluación (regla 3).
        $copias = [];

        foreach ($evaluaciones as $posicion => $e) {
            for ($orden = 1; $orden <= $tiposEvaluacion[$e['tipo']]; $orden++) {
                $copias[] = ['evaluacion_id' => $idsEvaluaciones[$posicion], 'descripcion' => "Paso $orden de la evaluación", 'orden' => $orden, 'created_at' => $e['fila']['created_at'], 'updated_at' => $e['fila']['created_at']];
            }
        }

        $idsCopias = insertarConIds('evaluacion_items', $copias);

        $estudiantesEvaluados = [];
        $cuenta = 0;
        $itemsDeLaEvaluacion = [];

        foreach ($evaluaciones as $posicion => $e) {
            $cantidadDeItems = $tiposEvaluacion[$e['tipo']];
            $itemsDeLaEvaluacion[$posicion] = array_slice($idsCopias, $cuenta, $cantidadDeItems);
            $cuenta += $cantidadDeItems;
            $s = $especificaciones[$e['indice']];
            $retirados = count($s['elegidos']) - $cantidadActiva[$e['indice']];

            foreach (array_slice($s['elegidos'], $retirados) as $estudiante) {
                $clavePar = $estudiante * 1000 + $e['tipo'];
                $intentosPrevios[$clavePar] = ($intentosPrevios[$clavePar] ?? 0) + 1;
                $finalizada = $e['fila']['estado'] === 'finalizada';
                $estudiantesEvaluados[] = ['posicion' => $posicion, 'fila' => [
                    'evaluacion_id' => $idsEvaluaciones[$posicion],
                    'estudiante_id' => $estudiante,
                    // El resultado lo decide el docente, no sale de los ítems (regla 2).
                    'resultado' => $finalizada ? (probabilidad(80) ? 'aprobado' : 'no_aprobado') : null,
                    'intento' => $intentosPrevios[$clavePar],
                    'observaciones' => null,
                    'created_at' => $e['fila']['created_at'],
                    'updated_at' => $e['fila']['updated_at'],
                ]];
            }

            if ($e['fila']['estado'] === 'finalizada') {
                $anotar(AccionAuditada::EvaluacionFinalizada, $s['docenteId'], Evaluacion::class, $idsEvaluaciones[$posicion], 'Finalizó la evaluación '.$idsEvaluaciones[$posicion].'.', CarbonImmutable::parse($e['fila']['updated_at']));
            }
        }

        $idsEvaluados = insertarConIds('evaluacion_estudiantes', array_column($estudiantesEvaluados, 'fila'));
        $cumplimientos = [];

        foreach ($estudiantesEvaluados as $k => $ee) {
            foreach ($itemsDeLaEvaluacion[$ee['posicion']] as $itemId) {
                $cumplimientos[] = ['evaluacion_estudiante_id' => $idsEvaluados[$k], 'evaluacion_item_id' => $itemId, 'cumplido' => probabilidad(75)];
            }
        }

        insertar('evaluacion_estudiante_item', $cumplimientos);
    });

    etapa(sprintf('periodo %s: %d solicitudes acumuladas', $nombrePeriodo, $resumen['solicitudes']));

    // Formatos de confidencialidad: uno por firmante y periodo (estudiantes y docentes).
    $firmantes = [...$poolEstudiantes, ...$docentes];
    $filasFormatos = [];

    foreach ($firmantes as $firmante) {
        $azar = azar(1, 100);

        if ($vigente) {
            $estado = $azar <= 55 ? 'verificado' : ($azar <= 75 ? 'cargado' : 'pendiente');
        } else {
            $estado = $azar <= 95 ? 'verificado' : 'cargado';
        }

        $fisico = $vigente && $estado === 'pendiente' && probabilidad(25);
        $entrega = $periodo['desde']->addDays(azar(3, 40))->setTime(azar(8, 17), 0);

        $filasFormatos[] = [
            'firmante_id' => $firmante,
            'plantilla_id' => $plantillaId,
            'periodo_academico' => $nombrePeriodo,
            'archivo_firmado_path' => $estado === 'pendiente' ? null : 'confidencialidad/firmados/'.$firmante.'-'.$nombrePeriodo.'.pdf',
            'estado' => $estado,
            'verificado_por' => $estado === 'verificado' ? elegir($administrativosPermanentes) : null,
            'verificado_at' => $estado === 'verificado' ? marca($entrega->addDays(2)) : null,
            'created_at' => marca($entrega),
            'updated_at' => marca($entrega->addDays(2)),
            'motivo_rechazo' => null,
            'recibido_fisico_at' => $fisico ? marca($entrega) : null,
            'recibido_fisico_por' => $fisico ? elegir($administrativosPermanentes) : null,
        ];
    }

    DB::transaction(function () use ($filasFormatos, $anotar): void {
        $idsFormatos = insertarConIds('formatos_confidencialidad', $filasFormatos);

        foreach ($filasFormatos as $i => $f) {
            if ($f['estado'] === 'verificado') {
                $anotar(AccionAuditada::FormatoVerificado, (int) $f['verificado_por'], FormatoConfidencialidad::class, $idsFormatos[$i], 'Verificó el formato de confidencialidad.', CarbonImmutable::parse($f['verificado_at']));

                if (probabilidad(70)) {
                    $anotar(AccionAuditada::FormatoDescargado, (int) $f['verificado_por'], FormatoConfidencialidad::class, $idsFormatos[$i], 'Descargó el formato firmado.', CarbonImmutable::parse($f['verificado_at'])->subHour());
                }
            }
        }
    });

    etapa("periodo $nombrePeriodo: ".count($filasFormatos).' formatos');
}

// ---------------------------------------------------------------------
// Bloqueos (RF68): vigentes y levantados
// ---------------------------------------------------------------------

DB::transaction(function () use ($estudiantesActivos, $docentes, $coordinadores, $hoy, $anotar): void {
    $filas = [];

    foreach (array_slice($estudiantesActivos, 0, 15) as $estudiante) {
        $filas[] = ['user_id' => $estudiante, 'motivo' => 'Inasistencias reiteradas sin aviso.', 'bloqueado_por' => $coordinadores[0], 'levantado_at' => null, 'levantado_por' => null, 'motivo_levantamiento' => null, 'created_at' => marca($hoy->subDays(azar(1, 30))), 'updated_at' => marca($hoy)];
    }

    $filas[] = ['user_id' => $docentes[40], 'motivo' => 'Incumplimiento de las normas del laboratorio.', 'bloqueado_por' => $coordinadores[0], 'levantado_at' => null, 'levantado_por' => null, 'motivo_levantamiento' => null, 'created_at' => marca($hoy->subDays(5)), 'updated_at' => marca($hoy)];

    foreach (array_slice($estudiantesActivos, 15, 25) as $estudiante) {
        $inicio = $hoy->subDays(azar(40, 90));
        $filas[] = ['user_id' => $estudiante, 'motivo' => 'Bloqueo temporal.', 'bloqueado_por' => $coordinadores[1], 'levantado_at' => marca($inicio->addDays(azar(2, 15))), 'levantado_por' => $coordinadores[1], 'motivo_levantamiento' => 'Se puso al día.', 'created_at' => marca($inicio), 'updated_at' => marca($inicio->addDays(10))];
    }

    $ids = insertarConIds('bloqueos', $filas);

    foreach ($filas as $i => $f) {
        $anotar(AccionAuditada::BloqueoRegistrado, $f['bloqueado_por'], 'App\Models\Bloqueo', $ids[$i], 'Bloqueó a una persona.', CarbonImmutable::parse($f['created_at']), $f['motivo']);
    }
});

// ---------------------------------------------------------------------
// Bitácora: lo auditado arriba, más el ruido de accesos negados y reportes que acumula un semestre
// ---------------------------------------------------------------------

$objetivoDeBitacora = 30000 * $factor;
$todosLosUsuarios = [...$administrativosPermanentes, ...$coordinadores, ...$docentes];

while (count($bitacora) < $objetivoDeBitacora) {
    $cuando = $hoy->subDays(azar(0, 400))->setTime(azar(7, 19), azar(0, 59));
    $quien = elegir($todosLosUsuarios);
    $accion = elegir([AccionAuditada::AccesoNegado, AccionAuditada::AccesoNegado, AccionAuditada::ReporteExportado, AccionAuditada::FormatoDescargado]);

    $bitacora[] = [
        'accion' => $accion->value,
        'user_id' => $quien,
        'auditable_type' => $accion === AccionAuditada::FormatoDescargado ? FormatoConfidencialidad::class : null,
        'auditable_id' => null,
        'descripcion' => $accion->etiqueta().' (prueba de rendimiento).',
        'motivo' => null,
        'created_at' => marca($cuando),
    ];
}

// En orden cronológico, como la va dejando la aplicación: los ids crecen con el tiempo.
usort($bitacora, static fn (array $a, array $b): int => $a['created_at'] <=> $b['created_at']);

DB::transaction(static function () use ($bitacora): void {
    insertar('bitacora', $bitacora);
});

etapa('bitácora: '.count($bitacora));

// El ANALYZE deja estadísticas al día: sin él, los planes de las primeras consultas se miden con estimaciones de tabla vacía.
// Solo surte efecto con el dueño de las tablas. Con el usuario de la aplicación (el del contenedor de
// producción, S.1) PostgreSQL no falla: se salta cada tabla con un aviso. Por eso, cuando se genera
// dentro del SUT, después se corre ANALYZE con el dueño (rendimiento/README.md).
try {
    DB::statement('ANALYZE');
} catch (Throwable) {
    fwrite(STDERR, "ANALYZE no se pudo correr con este usuario; córrelo con el dueño de la base.\n");
}

fwrite(STDERR, "Si la base no es tuya (usuario de la aplicación), corre ANALYZE con el dueño antes de medir.\n");

// ---------------------------------------------------------------------
// Resumen
// ---------------------------------------------------------------------

$tablas = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename");
fwrite(STDERR, "\nFilas por tabla:\n");

foreach ($tablas as $tabla) {
    $cuantas = (int) DB::table($tabla->tablename)->count();

    if ($cuantas > 0) {
        fwrite(STDERR, sprintf("  %-32s %9d\n", $tabla->tablename, $cuantas));
    }
}

fwrite(STDERR, sprintf("\nSolicitudes sin sala por falta de huecos: %d; descartadas por cruces: %d\n", $resumen['sin_sala'], $resumen['descartadas']));
fwrite(STDERR, sprintf("Tiempo total: %.1f s\n", microtime(true) - $inicio));
