<?php

declare(strict_types=1);

use App\Enums\Reporte;
use App\Enums\Rol;
use App\Exports\ReporteExport;
use App\Http\Requests\FiltroDeReporteRequest;
use App\Livewire\Reportes\PantallaDeReportes;
use App\Models\CasoClinico;
use App\Models\ListaDeReposicion;
use App\Models\Materia;
use App\Models\Preparacion;
use App\Models\Sala;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\AsignacionDeRolService;
use App\Services\FiltroReporte;
use App\Services\GeneradorDeReportes;
use App\Services\ReporteService;
use Database\Seeders\RolSeeder;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

/*
 * Pantalla de reportes (RF54-RF56) y sus descargas. Las consultas ya están
 * probadas en los Reporte*Test; aquí se prueba que la pantalla y las
 * descargas las usan con el mismo filtro, y quién llega a ellas.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->coordinadora = User::factory()->coordinador()->create();
});

/** Uso de escenarios de una docente en una sala, en abril. */
function usoDeEscenarioDe(User $docente, ?Sala $sala = null, string $fecha = '2026-04-10'): Solicitud
{
    $solicitud = Solicitud::factory()->aprobada()->create([
        'docente_id' => $docente->id,
        'materia_id' => Materia::factory()->create()->id,
        'caso_clinico_id' => CasoClinico::factory()->create()->id,
        'fecha' => $fecha,
    ]);
    Preparacion::factory()->create(['solicitud_id' => $solicitud->id, 'sala_id' => ($sala ?? Sala::factory()->create())->id]);

    return $solicitud;
}

function pantallaDeReportes(array $parametros = []): mixed
{
    return Livewire::withQueryParams($parametros)->actingAs(test()->coordinadora)->test(PantallaDeReportes::class);
}

// ---------------------------------------------------------------------
// Quién entra
// ---------------------------------------------------------------------

it('abre la pantalla a coordinación y al ADMIN', function (Rol $rol): void {
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);

    $this->actingAs($usuario->fresh())
        ->get(route('panel.reportes'))
        ->assertOk()
        ->assertSeeLivewire(PantallaDeReportes::class);
})->with([Rol::Coordinador, Rol::Admin]);

it('no abre la pantalla ni las descargas a quien no genera reportes', function (Rol $rol): void {
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);
    $this->actingAs($usuario->fresh());

    $this->get(route('panel.reportes'))->assertForbidden();
    $this->get(route('panel.reportes.excel', ['reporte' => Reporte::UsoDeEscenarios]))->assertForbidden();
    $this->get(route('panel.reportes.pdf', ['reporte' => Reporte::UsoDeEscenarios]))->assertForbidden();
})->with([Rol::Administrativo, Rol::Docente, Rol::Estudiante]);

it('deja de enseñar el reporte si el rol activo cambia con la pantalla abierta', function (): void {
    // Las acciones de la pantalla van a /livewire/update, no a la ruta del
    // panel. Aquí la usuaria pasa a docente en otra pestaña: su rol sigue
    // vigente, así que el middleware no corta, y la pantalla tiene que
    // volver a preguntar al Gate.
    $usuaria = User::factory()->coordinador()->docente()->create()->fresh();

    $this->actingAs($usuaria)->post(route('panel.rol-activo'), ['rol' => Rol::Coordinador->value]);
    $instantanea = instantaneaDeLaPantalla(route('panel.reportes'));

    $this->post(route('panel.rol-activo'), ['rol' => Rol::Docente->value]);
    app()->forgetScopedInstances();

    accionDeLivewire($instantanea, 'limpiarFiltros')->assertForbidden();
});

it('sigue respondiendo a la coordinadora que no cambió de rol', function (): void {
    // El control del test anterior: la misma acción, con el rol intacto.
    $usuaria = User::factory()->coordinador()->docente()->create()->fresh();

    $this->actingAs($usuaria)->post(route('panel.rol-activo'), ['rol' => Rol::Coordinador->value]);
    $instantanea = instantaneaDeLaPantalla(route('panel.reportes'));
    app()->forgetScopedInstances();

    accionDeLivewire($instantanea, 'limpiarFiltros')->assertOk();
});

// ---------------------------------------------------------------------
// La pantalla
// ---------------------------------------------------------------------

it('enseña el uso de escenarios por defecto, con sus columnas', function (): void {
    usoDeEscenarioDe(User::factory()->docente()->create(['nombre' => 'Ana Gómez']), Sala::factory()->create(['nombre' => 'Sala de partos']));

    pantallaDeReportes()
        ->assertSee('Caso clínico')
        ->assertSee('Horas')
        ->assertSee('Ana Gómez')
        ->assertSee('Sala de partos')
        ->assertSee('1 registro');
});

it('cambia de reporte y esconde el filtro de sala donde no aplica', function (): void {
    $columnas = fn (string $columna) => fn (array $encabezados): bool => in_array($columna, $encabezados, true);

    pantallaDeReportes()
        ->assertSeeHtml('id="sala"')
        ->set('reporte', Reporte::ResultadosDeEvaluacion->value)
        ->assertSee(Reporte::ResultadosDeEvaluacion->descripcion())
        ->assertViewHas('encabezados', $columnas('Tipo de evaluación'))
        ->assertDontSeeHtml('id="sala"')
        ->set('reporte', Reporte::EvaluacionesNoRegistradas->value)
        ->assertViewHas('encabezados', $columnas('Motivo'));
});

it('filtra la tabla por docente', function (): void {
    $ana = User::factory()->docente()->create(['nombre' => 'Ana Gómez']);
    $luis = User::factory()->docente()->create(['nombre' => 'Luis Pérez']);
    usoDeEscenarioDe($ana);
    usoDeEscenarioDe($luis);

    pantallaDeReportes()
        ->assertSee('2 registros')
        ->set('docente', (string) $ana->id)
        ->assertSee('1 registro')
        ->assertViewHas('filas', fn ($filas): bool => $filas->total() === 1 && $filas->first()[0] === 'Ana Gómez');
});

it('no consulta ni ofrece descargas con un rango de fechas al revés', function (): void {
    usoDeEscenarioDe(User::factory()->docente()->create());

    pantallaDeReportes()
        ->set('desde', '2026-05-01')
        ->set('hasta', '2026-04-01')
        ->assertHasErrors(['hasta'])
        ->assertSee('La fecha final no puede ser anterior a la inicial.')
        ->assertViewHas('filas', fn ($filas): bool => $filas === null)
        ->assertDontSee('Descargar Excel')
        ->set('hasta', '2026-05-31')
        ->assertHasNoErrors()
        ->assertSee('Descargar Excel');
});

it('valida también los filtros que llegan en la URL', function (): void {
    // Un marcador guardado o un enlace escrito a mano no pasan por los campos.
    usoDeEscenarioDe(User::factory()->docente()->create());

    pantallaDeReportes(['desde' => '2026-05-01', 'hasta' => '2026-04-01'])
        ->assertHasErrors(['hasta'])
        ->assertViewHas('filas', fn ($filas): bool => $filas === null);
});

it('vuelve a la primera página al cambiar un filtro', function (): void {
    // Si no, en la página 2 de un resultado que ahora cabe en una sola
    // se vería una tabla vacía.
    Solicitud::factory()->deEvaluacion()->aprobada()->count(ReporteService::POR_PAGINA + 1)->create(['fecha' => now()->subWeek()]);

    pantallaDeReportes(['reporte' => Reporte::EvaluacionesNoRegistradas->value])
        ->call('gotoPage', 2)
        ->assertSet('paginators.page', 2)
        ->set('desde', now()->subMonth()->toDateString())
        ->assertSet('paginators.page', 1);
});

it('pone en los enlaces de descarga los filtros que se están viendo', function (): void {
    $docente = User::factory()->docente()->create();
    $materia = Materia::factory()->create();
    $sala = Sala::factory()->create();
    usoDeEscenarioDe($docente, $sala);

    $filtros = ['desde' => '2026-04-01', 'hasta' => '2026-04-30', 'docente' => (string) $docente->id, 'materia' => (string) $materia->id, 'sala' => (string) $sala->id];

    pantallaDeReportes($filtros)
        ->assertSee(route('panel.reportes.excel', ['reporte' => Reporte::UsoDeEscenarios, ...$filtros]))
        ->assertSee(route('panel.reportes.pdf', ['reporte' => Reporte::UsoDeEscenarios, ...$filtros]));
});

it('no manda la sala en la descarga de un reporte que no sabe de salas', function (): void {
    $sala = Sala::factory()->create();

    pantallaDeReportes(['reporte' => Reporte::ResultadosDeEvaluacion->value, 'sala' => (string) $sala->id, 'desde' => '2026-04-01'])
        ->assertSee(route('panel.reportes.excel', ['reporte' => Reporte::ResultadosDeEvaluacion, 'desde' => '2026-04-01']))
        ->assertDontSee('sala=');
});

it('quita todos los filtros de una vez', function (): void {
    pantallaDeReportes(['desde' => '2026-04-01', 'hasta' => '2026-04-30'])
        ->call('limpiarFiltros')
        ->assertSet('desde', '')
        ->assertSet('hasta', '');
});

it('pagina el reporte de veinticinco en veinticinco', function (): void {
    Solicitud::factory()->deEvaluacion()->aprobada()->count(ReporteService::POR_PAGINA + 1)->create(['fecha' => now()->subWeek()]);

    pantallaDeReportes(['reporte' => Reporte::EvaluacionesNoRegistradas->value])
        ->assertSee((ReporteService::POR_PAGINA + 1).' registros')
        ->assertViewHas('filas', fn ($filas): bool => $filas->count() === ReporteService::POR_PAGINA && $filas->lastPage() === 2);
});

it('vuelve al primer reporte si la URL pide uno que no es de esta pantalla', function (): void {
    pantallaDeReportes(['reporte' => Reporte::ListaDeReposicion->value])
        ->assertSet('reporte', Reporte::UsoDeEscenarios->value);
});

it('ofrece en el filtro a los docentes con solicitudes, aunque ya no tengan el rol', function (): void {
    // Regla 13: el rol temporal vence, pero lo que hizo con él sigue en los
    // reportes, y tiene que poder filtrarse.
    $conHistorial = User::factory()->docente()->create(['nombre' => 'Ana Gómez']);
    usoDeEscenarioDe($conHistorial);

    $pasante = User::factory()->create(['nombre' => 'Pedro Pasante']);
    app(AsignacionDeRolService::class)->asignar(User::factory()->admin()->create(), $pasante, Rol::Docente, hasta: now()->toDateString());
    usoDeEscenarioDe($pasante);

    User::factory()->docente()->create(['nombre' => 'Sin Solicitudes']);

    $this->travel(1)->days();

    expect(app(ReporteService::class)->docentesConSolicitudes()->pluck('nombre')->all())
        ->toBe(['Ana Gómez', 'Pedro Pasante']);
});

// ---------------------------------------------------------------------
// Las descargas
// ---------------------------------------------------------------------

it('descarga el Excel con el mismo filtro que la pantalla', function (): void {
    Excel::fake();
    $ana = User::factory()->docente()->create();
    usoDeEscenarioDe($ana);
    usoDeEscenarioDe(User::factory()->docente()->create());

    $this->actingAs($this->coordinadora)
        ->get(route('panel.reportes.excel', ['reporte' => Reporte::UsoDeEscenarios, 'docente' => $ana->id]))
        ->assertOk();

    Excel::assertDownloaded(
        Reporte::UsoDeEscenarios->nombreDeArchivo().'.xlsx',
        fn (ReporteExport $exportacion): bool => $exportacion->query()->get()->count() === 1,
    );
});

it('descarga el PDF de cada reporte', function (Reporte $reporte): void {
    $respuesta = $this->actingAs($this->coordinadora)->get(route('panel.reportes.pdf', ['reporte' => $reporte]));

    $respuesta->assertOk()->assertDownload("{$reporte->nombreDeArchivo()}.pdf");
    expect((string) $respuesta->getContent())->toStartWith('%PDF');
})->with(Reporte::agregados());

it('no descarga por aquí la lista de reposición', function (): void {
    // Tiene sus propias descargas, que exigen una lista cerrada (RF67).
    $lista = ListaDeReposicion::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get("/panel/reportes/lista_de_reposicion/excel?lista={$lista->id}")
        ->assertNotFound();
});

it('rechaza una descarga con filtros inválidos', function (): void {
    $this->actingAs($this->coordinadora)
        ->get(route('panel.reportes.excel', ['reporte' => Reporte::UsoDeEscenarios, 'desde' => '2026-05-01', 'hasta' => '2026-04-01']))
        ->assertSessionHasErrors(['hasta']);
});

it('aplica la sala solo al reporte que sabe de salas', function (): void {
    expect(FiltroDeReporteRequest::filtro(Reporte::UsoDeEscenarios, ['sala' => '7'])->salaId)->toBe(7)
        ->and(FiltroDeReporteRequest::filtro(Reporte::ResultadosDeEvaluacion, ['sala' => '7'])->salaId)->toBeNull()
        ->and(FiltroDeReporteRequest::filtro(Reporte::EvaluacionesNoRegistradas, ['sala' => '7'])->salaId)->toBeNull();
});

it('descarga el PDF con la descripción completa del filtro en la cabecera', function (): void {
    $docente = User::factory()->docente()->create(['nombre' => 'Ana Gómez']);
    $cabecera = null;
    View::composer(Reporte::UsoDeEscenarios->plantillaPdf(), function ($vista) use (&$cabecera): void {
        $cabecera = $vista->getData()['descripcionDelFiltro'];
    });

    $this->actingAs($this->coordinadora)
        ->get(route('panel.reportes.pdf', ['reporte' => Reporte::UsoDeEscenarios, 'docente' => $docente->id]))
        ->assertOk();

    expect($cabecera)->toBe('Todo el histórico · Docente: Ana Gómez');
});

it('escribe en la cabecera del PDF todos los filtros, no solo las fechas', function (): void {
    // Un archivo descargado circula solo: filtrado por un docente y sin
    // decirlo, parecería el total del laboratorio.
    $docente = User::factory()->docente()->create(['nombre' => 'Ana Gómez']);
    $materia = Materia::factory()->create(['nombre' => 'Cuidado materno']);
    $sala = Sala::factory()->create(['nombre' => 'Sala de partos']);

    $descripcion = app(GeneradorDeReportes::class)->describir(
        new FiltroReporte(desde: '2026-04-01', hasta: '2026-04-30', docenteId: $docente->id, materiaId: $materia->id, salaId: $sala->id),
    );

    expect($descripcion)->toBe('Del 2026-04-01 al 2026-04-30 · Docente: Ana Gómez · Materia: Cuidado materno · Sala: Sala de partos')
        ->and(app(GeneradorDeReportes::class)->describir(new FiltroReporte))->toBe('Todo el histórico');
});
