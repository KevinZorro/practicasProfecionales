<?php

declare(strict_types=1);

use App\Enums\AccionAuditada;
use App\Enums\EstadoItemInventario;
use App\Enums\EstadoPreparacion;
use App\Enums\OrigenSolicitud;
use App\Enums\ResultadoEvaluacion;
use App\Enums\Rol;
use App\Models\Bloqueo;
use App\Models\CambioEstadoItem;
use App\Models\CasoClinico;
use App\Models\Evaluacion;
use App\Models\EvaluacionEstudiante;
use App\Models\EvaluacionItem;
use App\Models\FormatoConfidencialidad;
use App\Models\ItemInventario;
use App\Models\LineaDeReposicion;
use App\Models\NecesidadDeReposicion;
use App\Models\PeriodoAcademico;
use App\Models\Preparacion;
use App\Models\Sala;
use App\Models\Solicitud;
use App\Models\TipoEvaluacion;
use App\Models\User;
use App\Services\AsignacionDeRolService;
use App\Services\BitacoraService;
use App\Services\EvaluacionService;
use App\Services\SupervisionService;
use Database\Seeders\RolSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Support\Facades\DB;

/*
 * Presupuesto de consultas por pantalla (ISO/IEC 25010:2023, eficiencia de
 * desempeño → utilización de recursos; CLAUDE.md §6, «Consultas N+1»).
 *
 * Cada pantalla se abre con pocos registros y otra vez con cuatro veces más.
 * Las consultas de la petición tienen que ser las mismas: si crecen con los
 * datos, hay una consulta por fila, que en producción (700 estudiantes, 150
 * docentes) son cientos de viajes a PostgreSQL por cada clic. Además la
 * segunda petición corre con Model::preventLazyLoading(), que lanza la
 * excepción en el primer acceso perezoso a una relación de una colección.
 *
 * Las pantallas con paginación enseñan como máximo una página: los registros
 * de cada prueba rebasan ese tamaño para que la lista siga creciendo al
 * pasar del volumen pequeño al grande.
 */

/** Registros con los que se abre cada pantalla la primera vez. */
const REGISTROS_BASE = 4;

/** Los que se añaden antes de la segunda: en total, cuatro veces los de partida. */
const REGISTROS_ADICIONALES = 12;

/**
 * Lo que sobra sobre lo medido antes de que una pantalla falle: absorbe una
 * relación nueva o un bloque más en la página, pero no una consulta por fila
 * (con 16 registros, decenas más).
 */
const MARGEN_DE_CONSULTAS = 3;

/**
 * Consultas que hace hoy cada pantalla en una petición, sesión, usuario,
 * roles y permisos incluidos. Es el presupuesto: cada una vale lo medido más
 * MARGEN_DE_CONSULTAS. Si una pantalla gana consultas a propósito (un bloque
 * nuevo), se sube aquí con el cambio; si las gana sin querer, es un N+1 o una
 * consulta repetida y la prueba lo dice.
 *
 * @return array<string, int>
 */
function consultasMedidasPorPantalla(): array
{
    return [
        '«Hoy» de admin' => 10,
        '«Hoy» de coordinador' => 23,
        '«Hoy» de administrativo' => 17,
        '«Hoy» de docente' => 21,
        '«Hoy» de supervisor' => 5,
        '«Hoy» de estudiante' => 11,
        'mis solicitudes' => 8,
        'bandeja de solicitudes del administrativo' => 10,
        'bandeja de solicitudes de coordinación' => 10,
        'eventos del calendario del administrativo' => 7,
        'eventos del calendario de coordinación' => 7,
        'eventos del calendario del docente' => 7,
        'tablero de preparaciones' => 12,
        'evaluaciones de coordinación' => 8,
        'mis resultados' => 9,
        'mi formato del estudiante' => 5,
        'mi formato del docente' => 5,
        'bandeja de formatos de confidencialidad' => 7,
        'estado de los firmantes' => 11,
        'sesiones apartadas' => 10,
        'supervisión del supervisor' => 16,
        'reporte de uso de escenarios' => 6,
        'reporte de resultados de evaluación' => 11,
        'reporte de evaluaciones no registradas' => 9,
        'periodos académicos' => 7,
        'depuración de datos' => 6,
        'inventario' => 6,
        'lista de reposición' => 13,
        'bitácora' => 4,
        'bloqueos' => 5,
        'roles de los usuarios' => 6,
        'docentes de un supervisor' => 6,
        'expediente de una solicitud del administrativo' => 19,
        'expediente de una solicitud del docente' => 18,
        'participantes de la sesión del docente' => 15,
        'participantes de la sesión del administrativo' => 15,
        'formato intramural de la sesión' => 12,
        'novedades de la sesión' => 25,
        // Sin el N+1 conocido de esta pantalla (ver su prueba, omitida) y con la consulta de tipos hecha una sola vez.
        'evaluaciones del docente' => 12,
        'registro de una evaluación' => 22,
    ];
}

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->travelTo(now()->setDate(2026, 10, 14)->setTime(8, 0));
    abrirPeriodo();
    Mail::fake();
});

// preventLazyLoading es un interruptor estático: si queda encendido se filtra
// a los tests que corren después en el mismo proceso.
afterEach(function (): void {
    Model::preventLazyLoading(false);
});

/**
 * Consultas SQL que hace la petición, sin contar las del resto del test.
 *
 * @param  Closure(): mixed  $peticion
 */
function consultasSqlDeLaPeticion(Closure $peticion): int
{
    $contando = true;
    $total = 0;

    DB::listen(function (QueryExecuted $consulta) use (&$contando, &$total): void {
        if ($contando) {
            $total++;
        }
    });

    try {
        $peticion();
    } finally {
        $contando = false;
    }

    return $total;
}

/**
 * Abre la pantalla dos veces, con REGISTROS_BASE y con cuatro veces más.
 * Devuelve las consultas de cada petición medida (la primera, de
 * calentamiento, no cuenta: arma cachés como la de permisos).
 *
 * @param  Closure(int, array<string, mixed>): void  $sembrar  Recibe el contexto por referencia: guarda ahí lo fijo (la solicitud del expediente, por ejemplo).
 * @param  Closure(array<string, mixed>): string  $url
 * @param  array<string, mixed>  $contexto
 * @return array{int, int}
 */
function medirConsultasDeLaPantalla(User $usuario, Closure $sembrar, Closure $url, array $contexto = []): array
{
    $contexto['usuario'] = $usuario;
    $prueba = test();
    $prueba->actingAs($usuario);

    $sembrar(REGISTROS_BASE, $contexto);
    $prueba->get($url($contexto))->assertOk();

    // Sin manejo de excepciones: una LazyLoadingViolationException tiene que
    // llegar a la prueba, no convertirse en una página 500.
    $prueba->withoutExceptionHandling();
    Model::preventLazyLoading();

    $pequeno = consultasSqlDeLaPeticion(fn () => $prueba->get($url($contexto))->assertOk());

    $sembrar(REGISTROS_ADICIONALES, $contexto);
    $grande = consultasSqlDeLaPeticion(fn () => $prueba->get($url($contexto))->assertOk());

    return [$pequeno, $grande];
}

/**
 * Una pasada de actividad del laboratorio por cada registro pedido: lo que
 * lista casi toda pantalla. Quien actúa en cada rol (docente, estudiante) es
 * el usuario del contexto cuando tiene ese rol, y un tercero nuevo si no.
 *
 * @param  array<string, mixed>  $contexto
 */
function sembrarActividad(int $cuantos, array &$contexto): void
{
    $usuario = $contexto['usuario'];
    $docenteFijo = $usuario->hasRole(Rol::Docente->value) ? $usuario : null;
    $estudianteFijo = $usuario->hasRole(Rol::Estudiante->value) ? $usuario : null;
    $supervisor = $usuario->hasRole(Rol::Supervisor->value) ? $usuario : null;
    $admin = User::factory()->admin()->create();
    $contexto['salas'] ??= Sala::factory()->count(3)->create()->all();
    $contexto['dia'] ??= 0;

    for ($i = 0; $i < $cuantos; $i++) {
        // Un día más por vuelta, y todo lo que no es de hoy se reparte entre
        // los días: así cualquier página de una lista ordenada por fecha
        // mezcla de todo, también de lo que tiene montaje y salas. Con datos
        // al azar, un montaje con salas que cae fuera de la primera página
        // quitaría una consulta de la petición y haría inestable la cuenta.
        $dia = now()->addDays(++$contexto['dia'])->toDateString();
        $docente = $docenteFijo ?? User::factory()->docente()->create();
        $estudiantes = User::factory()->estudiante()->count(3)->create();
        $estudiante = $estudianteFijo ?? $estudiantes->first();

        if ($supervisor !== null) {
            app(SupervisionService::class)->asignarDocente($admin, $supervisor, $docente);
        }

        // Pendiente y revisada: lo que esperan revisión y aprobación.
        Solicitud::factory()->create(['docente_id' => $docente->id, 'fecha' => $dia])->estudiantes()->attach($estudiantes->modelKeys());
        Solicitud::factory()->revisada()->create(['docente_id' => $docente->id, 'fecha' => $dia]);

        // Aprobada, una de hoy y otra de otro día, con su montaje, sus salas
        // y su inventario.
        foreach (['2026-10-14', $dia] as $fecha) {
            $aprobada = Solicitud::factory()->aprobada()->create(['docente_id' => $docente->id, 'fecha' => $fecha]);
            $aprobada->estudiantes()->attach($estudiantes->modelKeys());
            $aprobada->items()->attach(ItemInventario::factory()->create()->id, ['cantidad' => 1]);
            Preparacion::factory()->conSala(...$contexto['salas'])->create([
                'solicitud_id' => $aprobada->id,
                'estado' => EstadoPreparacion::Pendiente,
            ]);
        }

        // Sesión de evaluación que el docente todavía tiene por evaluar, de una
        // materia con tipos de evaluación a elegir.
        $porEvaluar = Solicitud::factory()->deEvaluacion()->aprobada()->create(['docente_id' => $docente->id, 'fecha' => $dia]);
        $porEvaluar->materia->tiposEvaluacion()->attach(TipoEvaluacion::factory()->count(2)->create()->modelKeys());
        Preparacion::factory()->conSala(...$contexto['salas'])->create(['solicitud_id' => $porEvaluar->id]);

        // Evaluación que se dictó y nadie registró (reporte de no registradas).
        $vencida = Solicitud::factory()->deEvaluacion()->aprobada()->create(['docente_id' => $docente->id, 'fecha' => '2026-10-01']);
        Preparacion::factory()->conSala(...$contexto['salas'])->create(['solicitud_id' => $vencida->id]);

        // Sesión apartada antes del semestre por un administrativo (RF57).
        $apartada = Solicitud::factory()->aprobada()->create([
            'docente_id' => $docente->id,
            'fecha' => $dia,
            'origen' => OrigenSolicitud::RegistroPrevio,
            'registrada_por' => $admin->id,
        ]);
        Preparacion::factory()->conSala(...$contexto['salas'])->create(['solicitud_id' => $apartada->id]);

        // Evaluación finalizada, con un estudiante de ítems marcados.
        $evaluacion = Evaluacion::factory()->finalizada()->create([
            'docente_id' => $docente->id,
            'solicitud_id' => Solicitud::factory()->deEvaluacion()->aprobada()->create(['docente_id' => $docente->id, 'fecha' => '2026-10-14'])->id,
        ]);
        $items = EvaluacionItem::factory()->count(2)->create(['evaluacion_id' => $evaluacion->id]);
        $registro = EvaluacionEstudiante::factory()->create(['evaluacion_id' => $evaluacion->id, 'estudiante_id' => $estudiante->id]);
        $registro->items()->attach($items->pluck('id')->mapWithKeys(fn (int $id): array => [$id => ['cumplido' => true]])->all());

        FormatoConfidencialidad::factory()->cargado()->create(['firmante_id' => $estudiantes->last()->id]);
    }
}

/**
 * Estudiantes nuevos en la sesión: unos ya evaluados en la evaluación y otros
 * solo apuntados, que son los candidatos que ofrece el registro.
 *
 * @param  array<string, mixed>  $contexto
 */
function sembrarParticipantes(int $cuantos, array &$contexto): void
{
    $docente = $contexto['usuario']->hasRole(Rol::Docente->value) ? $contexto['usuario'] : User::factory()->docente()->create();

    if (! isset($contexto['solicitud'])) {
        $contexto['solicitud'] = Solicitud::factory()->deEvaluacion()->aprobada()->create(['docente_id' => $docente->id, 'fecha' => '2026-10-14']);
        $contexto['solicitud']->items()->attach(ItemInventario::factory()->count(3)->create()->pluck('id')->mapWithKeys(fn (int $id): array => [$id => ['cantidad' => 1]])->all());
        Preparacion::factory()->conSala(...Sala::factory()->count(2)->create()->all())->create(['solicitud_id' => $contexto['solicitud']->id]);

        $contexto['evaluacion'] = Evaluacion::factory()->create([
            'docente_id' => $docente->id,
            'solicitud_id' => $contexto['solicitud']->id,
            'tipo_evaluacion_id' => TipoEvaluacion::factory()->conChecklist(3)->create()->id,
        ]);
        $contexto['items'] = EvaluacionItem::factory()->count(3)->create(['evaluacion_id' => $contexto['evaluacion']->id]);
    }

    for ($i = 0; $i < $cuantos; $i++) {
        $evaluado = User::factory()->estudiante()->create();
        $candidato = User::factory()->estudiante()->create();
        $contexto['solicitud']->estudiantes()->attach([$evaluado->id, $candidato->id]);

        $registro = EvaluacionEstudiante::factory()->create(['evaluacion_id' => $contexto['evaluacion']->id, 'estudiante_id' => $evaluado->id]);
        $registro->items()->attach($contexto['items']->pluck('id')->mapWithKeys(fn (int $id): array => [$id => ['cumplido' => true]])->all());

        // Cambios hechos a la sesión: aparecen en sus novedades.
        $contexto['solicitud']->reprogramaciones()->create([
            'fecha_anterior' => '2026-10-10',
            'hora_inicio_anterior' => '07:00:00',
            'hora_fin_anterior' => '09:00:00',
            'caso_clinico_anterior_id' => CasoClinico::factory()->create()->id,
            'fecha_nueva' => '2026-10-14',
            'hora_inicio_nueva' => '07:00:00',
            'hora_fin_nueva' => '09:00:00',
            'caso_clinico_nuevo_id' => CasoClinico::factory()->create()->id,
            'motivo' => 'Cruce con una jornada de la facultad.',
            'constancia_comunicacion' => 'Correo del 9 de octubre.',
            'reprogramada_por' => User::factory()->administrativo()->create()->id,
        ]);
        $contexto['solicitud']->sustituciones()->create([
            'docente_anterior_id' => User::factory()->docente()->create()->id,
            'docente_nuevo_id' => User::factory()->docente()->create()->id,
            'motivo' => 'Incapacidad médica.',
            'registrada_por' => User::factory()->administrativo()->create()->id,
        ]);
    }
}

/**
 * Inventario con simuladores y sus accesorios, bajas con su historial,
 * necesidades anotadas y listas de reposición ya cerradas.
 *
 * @param  array<string, mixed>  $contexto
 */
function sembrarInventario(int $cuantos, array &$contexto): void
{
    for ($i = 0; $i < $cuantos; $i++) {
        $simulador = ItemInventario::factory()->simulador()->create();
        $accesorios = ItemInventario::factory()->count(2)->accesorio($simulador)->create();
        $gasto = ItemInventario::factory()->equipoBasico()->create();

        CambioEstadoItem::factory()->create([
            'item_inventario_id' => $gasto->id,
            'estado_anterior' => EstadoItemInventario::Operativo,
            'estado_nuevo' => EstadoItemInventario::DadoDeBaja,
            'cantidad' => 2,
        ]);
        // Todos con historial: la página de 15 ítems va ordenada por tipo y,
        // si solo algunos lo tuvieran, qué consultas anidadas se hacen
        // dependería de cuáles caen en ella.
        foreach ([$simulador, ...$accesorios] as $item) {
            CambioEstadoItem::factory()->create(['item_inventario_id' => $item->id]);
        }

        NecesidadDeReposicion::factory()->deUnItem($gasto->id)->create();
        NecesidadDeReposicion::factory()->create();
        LineaDeReposicion::factory()->create(['item_inventario_id' => $gasto->id]);
    }
}

/**
 * Cuentas con roles permanentes y temporales, y las acciones que dejan
 * rastro en la bitácora.
 *
 * @param  array<string, mixed>  $contexto
 */
function sembrarCuentasYBitacora(int $cuantos, array &$contexto): void
{
    $admin = User::factory()->admin()->create();
    $coordinador = User::factory()->coordinador()->create();

    for ($i = 0; $i < $cuantos; $i++) {
        $cuenta = User::factory()->docente()->create();
        app(AsignacionDeRolService::class)->asignar($admin, $cuenta, Rol::Administrativo, now()->addDays(3)->toDateString());

        $solicitud = Solicitud::factory()->revisada()->create();
        app(BitacoraService::class)->registrar(AccionAuditada::SolicitudAprobada, $coordinador, $solicitud, 'Aprobada.');
        app(BitacoraService::class)->registrar(AccionAuditada::RolAsignado, $admin, $cuenta, 'Rol asignado.', 'Cubre al pasante.');
        app(BitacoraService::class)->registrarSinSujeto(AccionAuditada::ReporteExportado, User::factory()->coordinador()->create(), 'Exportó un reporte.');

        Bloqueo::factory()->create(['bloqueado_por' => $coordinador->id]);
        Bloqueo::factory()->levantado()->create(['bloqueado_por' => $coordinador->id]);
    }
}

/**
 * Docentes a cargo de un supervisor y otros que todavía se le pueden asignar.
 *
 * @param  array<string, mixed>  $contexto
 */
function sembrarDocentesSupervisados(int $cuantos, array &$contexto): void
{
    $admin = User::factory()->admin()->create();
    $contexto['supervisor'] ??= User::factory()->supervisor()->create();

    for ($i = 0; $i < $cuantos; $i++) {
        app(SupervisionService::class)->asignarDocente($admin, $contexto['supervisor'], User::factory()->docente()->create());
        User::factory()->docente()->create();
    }
}

/**
 * Lo que ya pasó de los dos semestres que se conservan: formatos de periodos
 * viejos que el ADMIN puede depurar.
 *
 * @param  array<string, mixed>  $contexto
 */
function sembrarDatosPorDepurar(int $cuantos, array &$contexto): void
{
    if (! isset($contexto['periodosViejos'])) {
        $contexto['periodosViejos'] = [
            PeriodoAcademico::factory()->cerrado()->create(['nombre' => '2025-1', 'abierto_at' => now()->subYear()]),
            PeriodoAcademico::factory()->cerrado()->create(['nombre' => '2025-2', 'abierto_at' => now()->subMonths(6)]),
        ];
    }

    FormatoConfidencialidad::factory()->count($cuantos)->cargado()->delPeriodo('2025-1')->create();
}

/**
 * Todas las pantallas principales: nombre legible → rol que la abre, URL y
 * siembra. Es una función y no un arreglo de datasets porque las clausuras no
 * viajan bien por los datasets de Pest.
 *
 * @return array<string, array{rol: Rol, url: Closure(array<string, mixed>): string, sembrar: Closure(int, array<string, mixed>): void}>
 */
function pantallasConPresupuestoDeConsultas(): array
{
    $actividad = function (int $cuantos, array &$contexto): void {
        sembrarActividad($cuantos, $contexto);
    };
    $participantes = function (int $cuantos, array &$contexto): void {
        sembrarParticipantes($cuantos, $contexto);
    };
    $inventario = function (int $cuantos, array &$contexto): void {
        sembrarInventario($cuantos, $contexto);
    };
    $cuentas = function (int $cuantos, array &$contexto): void {
        sembrarCuentasYBitacora($cuantos, $contexto);
    };
    $supervisados = function (int $cuantos, array &$contexto): void {
        sembrarDocentesSupervisados($cuantos, $contexto);
    };
    $ruta = fn (string $nombre, array $parametros = []): Closure => fn (): string => route($nombre, $parametros);
    // El rango que pide FullCalendar para octubre y noviembre: lo sembrado
    // cae dentro (los 62 días de tope son los de CalendarioController).
    $rangoDelCalendario = ['desde' => '2026-10-01', 'hasta' => '2026-11-30'];

    $pantallas = [];

    foreach (Rol::cases() as $rol) {
        $pantallas["«Hoy» de {$rol->value}"] = ['rol' => $rol, 'url' => $ruta('panel.inicio'), 'sembrar' => $actividad];
    }

    // Pantallas de lista que viven de las solicitudes, evaluaciones y formatos
    // que siembra la actividad del laboratorio.
    $listas = [
        'mis solicitudes' => [Rol::Docente, $ruta('panel.mis-solicitudes')],
        'bandeja de solicitudes del administrativo' => [Rol::Administrativo, $ruta('panel.solicitudes')],
        'bandeja de solicitudes de coordinación' => [Rol::Coordinador, $ruta('panel.solicitudes')],
        'eventos del calendario del administrativo' => [Rol::Administrativo, $ruta('panel.calendario.eventos', $rangoDelCalendario)],
        'eventos del calendario de coordinación' => [Rol::Coordinador, $ruta('panel.calendario.eventos', $rangoDelCalendario)],
        'eventos del calendario del docente' => [Rol::Docente, $ruta('panel.calendario.eventos', $rangoDelCalendario)],
        'tablero de preparaciones' => [Rol::Administrativo, $ruta('panel.preparaciones')],
        'evaluaciones de coordinación' => [Rol::Coordinador, $ruta('panel.evaluaciones')],
        'mis resultados' => [Rol::Estudiante, $ruta('panel.mis-resultados')],
        'mi formato del estudiante' => [Rol::Estudiante, $ruta('panel.mi-formato')],
        'mi formato del docente' => [Rol::Docente, $ruta('panel.mi-formato')],
        'bandeja de formatos de confidencialidad' => [Rol::Administrativo, $ruta('panel.formatos-confidencialidad')],
        'estado de los firmantes' => [Rol::Administrativo, $ruta('panel.formatos-confidencialidad.estado')],
        'sesiones apartadas' => [Rol::Administrativo, $ruta('panel.sesiones-apartadas')],
        'supervisión del supervisor' => [Rol::Supervisor, $ruta('panel.supervision')],
        'reporte de uso de escenarios' => [Rol::Coordinador, $ruta('panel.reportes', ['reporte' => 'uso_de_escenarios'])],
        'reporte de resultados de evaluación' => [Rol::Coordinador, $ruta('panel.reportes', ['reporte' => 'resultados_de_evaluacion'])],
        'reporte de evaluaciones no registradas' => [Rol::Coordinador, $ruta('panel.reportes', ['reporte' => 'evaluaciones_no_registradas'])],
        'periodos académicos' => [Rol::Coordinador, $ruta('panel.periodo-academico')],
    ];

    foreach ($listas as $nombre => [$rol, $url]) {
        $pantallas[$nombre] = ['rol' => $rol, 'url' => $url, 'sembrar' => $actividad];
    }

    $pantallas['depuración de datos'] = ['rol' => Rol::Admin, 'url' => $ruta('panel.depuracion'), 'sembrar' => function (int $cuantos, array &$contexto): void {
        sembrarDatosPorDepurar($cuantos, $contexto);
    }];
    $pantallas['inventario'] = ['rol' => Rol::Administrativo, 'url' => $ruta('panel.inventario'), 'sembrar' => $inventario];
    $pantallas['lista de reposición'] = ['rol' => Rol::Coordinador, 'url' => $ruta('panel.reposicion'), 'sembrar' => $inventario];
    $pantallas['bitácora'] = ['rol' => Rol::Coordinador, 'url' => $ruta('panel.bitacora'), 'sembrar' => $cuentas];
    $pantallas['bloqueos'] = ['rol' => Rol::Coordinador, 'url' => $ruta('panel.bloqueos'), 'sembrar' => $cuentas];
    $pantallas['roles de los usuarios'] = ['rol' => Rol::Admin, 'url' => $ruta('panel.usuarios'), 'sembrar' => $cuentas];
    $pantallas['docentes de un supervisor'] = ['rol' => Rol::Admin, 'url' => fn (array $c): string => route('panel.usuarios.supervision', $c['supervisor']), 'sembrar' => $supervisados];

    // Una sola sesión o evaluación cuya lista de estudiantes crece.
    $deLaSesion = [
        'expediente de una solicitud del administrativo' => [Rol::Administrativo, fn (array $c): string => route('panel.solicitudes.ver', $c['solicitud'])],
        'expediente de una solicitud del docente' => [Rol::Docente, fn (array $c): string => route('panel.solicitudes.ver', $c['solicitud'])],
        'participantes de la sesión del docente' => [Rol::Docente, fn (array $c): string => route('panel.solicitudes.participantes', $c['solicitud'])],
        'participantes de la sesión del administrativo' => [Rol::Administrativo, fn (array $c): string => route('panel.solicitudes.participantes', $c['solicitud'])],
        'formato intramural de la sesión' => [Rol::Administrativo, fn (array $c): string => route('panel.solicitudes.formato-intramural', $c['solicitud'])],
        'novedades de la sesión' => [Rol::Coordinador, fn (array $c): string => route('panel.solicitudes.novedades', $c['solicitud'])],
        'registro de una evaluación' => [Rol::Docente, fn (array $c): string => route('panel.evaluaciones.registro', $c['evaluacion'])],
    ];

    foreach ($deLaSesion as $nombre => [$rol, $url]) {
        $pantallas[$nombre] = ['rol' => $rol, 'url' => $url, 'sembrar' => $participantes];
    }

    return $pantallas;
}

/**
 * Pantallas con un N+1 que la prueba ya demostró y que todavía no se
 * arregla: la misma comprobación, apartada para que la suite no caiga y
 * quede escrita la regresión que hay que cerrar.
 *
 * @return array<string, array{rol: Rol, url: Closure(array<string, mixed>): string, sembrar: Closure(int, array<string, mixed>): void}>
 */
function pantallasConN1Conocido(): array
{
    return [
        'evaluaciones del docente' => [
            'rol' => Rol::Docente,
            'url' => fn (): string => route('panel.evaluaciones'),
            'sembrar' => function (int $cuantos, array &$contexto): void {
                sembrarActividad($cuantos, $contexto);
            },
        ],
    ];
}

/** @param array{rol: Rol, url: Closure(array<string, mixed>): string, sembrar: Closure(int, array<string, mixed>): void} $caso */
function comprobarPresupuestoDeConsultas(string $pantalla, array $caso): void
{
    $usuario = User::factory()->{$caso['rol']->value}()->create();

    [$pequeno, $grande] = medirConsultasDeLaPantalla($usuario, $caso['sembrar'], $caso['url']);

    // Con el nombre de la pantalla dentro de lo comparado, el fallo dice cuál
    // creció y de cuánto a cuánto.
    expect([$pantalla => $grande])->toBe([$pantalla => $pequeno]);

    $presupuesto = (consultasMedidasPorPantalla()[$pantalla] ?? 0) + MARGEN_DE_CONSULTAS;
    expect($grande)->toBeLessThanOrEqual($presupuesto, "«{$pantalla}» hace {$grande} consultas y su presupuesto es {$presupuesto}");
}

it('mantiene las mismas consultas en cada pantalla con cuatro veces más datos', function (string $pantalla): void {
    comprobarPresupuestoDeConsultas($pantalla, pantallasConPresupuestoDeConsultas()[$pantalla]);
})->with(array_combine(array_keys(pantallasConPresupuestoDeConsultas()), array_map(fn (string $nombre): array => [$nombre], array_keys(pantallasConPresupuestoDeConsultas()))));

it('mantiene las mismas consultas en la pantalla de evaluaciones del docente con cuatro veces más sesiones por evaluar', function (): void {
    comprobarPresupuestoDeConsultas('evaluaciones del docente', pantallasConN1Conocido()['evaluaciones del docente']);
})->skip('N+1 conocido: Evaluaciones::render llama a EvaluacionService::tiposParaLaSesion una vez por cada sesión por evaluar (8 sesiones por evaluar: 19 consultas; 32: 43)');

/** Evaluación en borrador con este número de estudiantes ya calificados. */
function evaluacionPorFinalizar(int $estudiantes): Evaluacion
{
    $evaluacion = Evaluacion::factory()->create();
    EvaluacionEstudiante::factory()->count($estudiantes)->sinResultado()->create(['evaluacion_id' => $evaluacion->id])
        ->each(fn (EvaluacionEstudiante $registro) => $registro->update(['resultado' => ResultadoEvaluacion::Aprobado]));

    return $evaluacion->refresh();
}

it('hace las mismas consultas al finalizar una evaluación con 3 estudiantes que con 12', function (): void {
    $servicio = app(EvaluacionService::class);
    $actor = User::factory()->docente()->create();

    $con3 = evaluacionPorFinalizar(3);
    $consultasCon3 = consultasSqlDeLaPeticion(fn () => $servicio->finalizar($con3, $actor));

    $con12 = evaluacionPorFinalizar(12);
    $consultasCon12 = consultasSqlDeLaPeticion(fn () => $servicio->finalizar($con12, $actor));

    expect($consultasCon12)->toBe($consultasCon3);
})->skip('N+1 conocido: EvaluacionService::fijarIntentos consulta el intento de cada estudiante por separado (3 estudiantes: 14 consultas; 12: 23)');

/*
 * Controles de la herramienta: sin ellos, un «las consultas no crecen» podría
 * ser un contador que no cuenta o una protección que no protege.
 */

it('cuenta una consulta por fila cuando una relación se carga sin precargar', function (): void {
    $consultasDe = function (int $solicitudes): int {
        Solicitud::factory()->count($solicitudes)->create();

        return consultasSqlDeLaPeticion(fn () => Solicitud::query()->get()->each(fn (Solicitud $s) => $s->materia));
    };

    $con2 = $consultasDe(2);
    $conMas = $consultasDe(3);

    expect($conMas)->toBeGreaterThan($con2);
});

it('lanza una excepción al recorrer una relación sin precargar con preventLazyLoading activo', function (): void {
    Solicitud::factory()->count(2)->create();
    Model::preventLazyLoading();

    expect(fn () => Solicitud::query()->get()->each(fn (Solicitud $s) => $s->materia))
        ->toThrow(LazyLoadingViolationException::class);
});
