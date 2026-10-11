<?php

declare(strict_types=1);

use App\Enums\EstadoPreparacion;
use App\Models\CasoClinico;
use App\Models\Evaluacion;
use App\Models\EvaluacionEstudiante;
use App\Models\FormatoConfidencialidad;
use App\Models\Preparacion;
use App\Models\Solicitud;
use App\Models\TipoEvaluacion;
use App\Models\User;
use App\Services\ResumenDelDiaService;
use Database\Seeders\RolSeeder;
use Illuminate\Database\Eloquent\Model;

/*
 * «Hoy» (/panel): lo que espera a cada persona con el rol que tiene puesto.
 * Cada bloque lo decide la Policy de su módulo (ResumenDelDiaService).
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->travelTo(now()->setDate(2026, 10, 14)->setTime(8, 0));
});

afterEach(function (): void {
    Model::preventLazyLoading(false);
});

/** Una práctica aprobada para hoy con su preparación. */
function escenarioDeHoy(string $caso, EstadoPreparacion $montaje = EstadoPreparacion::Pendiente): Solicitud
{
    $solicitud = Solicitud::factory()->aprobada()->create([
        'fecha' => '2026-10-14',
        'caso_clinico_id' => CasoClinico::factory()->create(['nombre' => $caso])->id,
    ]);
    Preparacion::factory()->create(['solicitud_id' => $solicitud->id, 'estado' => $montaje]);

    return $solicitud;
}

function solicitudDelCaso(string $caso, array $atributos = [], ?string $estado = null): Solicitud
{
    $fabrica = Solicitud::factory();
    $fabrica = $estado === null ? $fabrica : $fabrica->{$estado}();

    return $fabrica->create([
        'caso_clinico_id' => CasoClinico::factory()->create(['nombre' => $caso])->id,
        ...$atributos,
    ]);
}

it('abre «Hoy» al entrar al panel, con la fecha del día', function (): void {
    $this->actingAs(User::factory()->docente()->create())
        ->get(route('panel.inicio'))
        ->assertOk()
        ->assertSee('Hoy')
        ->assertSee('Miércoles 14 de octubre');
});

it('enseña a coordinación lo que espera su aprobación, los escenarios de hoy y lo que falta revisar', function (): void {
    solicitudDelCaso('Herida por arma de fuego', ['fecha' => '2026-10-20'], 'revisada');
    escenarioDeHoy('Reanimación neonatal');
    solicitudDelCaso('Atención de parto normal', ['fecha' => '2026-10-16']);

    $this->actingAs(User::factory()->coordinador()->create())
        ->get(route('panel.inicio'))
        ->assertOk()
        ->assertSeeInOrder(['Por aprobar', 'Herida por arma de fuego', 'Escenarios de hoy', 'Reanimación neonatal', 'Por revisar', 'Atención de parto normal']);
});

it('pone primero al administrativo los escenarios de hoy, y no le enseña lo que aprueba coordinación', function (): void {
    solicitudDelCaso('Herida por arma de fuego', [], 'revisada');
    escenarioDeHoy('Reanimación neonatal');
    solicitudDelCaso('Atención de parto normal');

    $this->actingAs(User::factory()->administrativo()->create())
        ->get(route('panel.inicio'))
        ->assertSeeInOrder(['Escenarios de hoy', 'Reanimación neonatal', 'Por revisar', 'Atención de parto normal'])
        ->assertDontSee('Por aprobar')
        ->assertDontSee('Herida por arma de fuego');
});

it('enseña al ADMIN lo que espera aprobación, pero no el montaje ni la revisión', function (): void {
    solicitudDelCaso('Herida por arma de fuego', [], 'revisada');
    escenarioDeHoy('Reanimación neonatal');

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('panel.inicio'))
        ->assertSee('Por aprobar')
        ->assertSee('Herida por arma de fuego')
        ->assertDontSee('Escenarios de hoy')
        ->assertDontSee('Por revisar');
});

it('dice cuántos escenarios de hoy faltan por montar', function (): void {
    escenarioDeHoy('Reanimación neonatal');
    escenarioDeHoy('Crisis convulsiva', EstadoPreparacion::Preparado);

    $this->actingAs(User::factory()->administrativo()->create())
        ->get(route('panel.inicio'))
        ->assertSee('Falta 1 de 2 por montar.');
});

it('dice que no hay nada cuando no hay nada, en vez de esconder el bloque', function (): void {
    $this->actingAs(User::factory()->coordinador()->create())
        ->get(route('panel.inicio'))
        ->assertSee('Nada espera tu aprobación.')
        ->assertSee('No hay escenarios aprobados para hoy.')
        ->assertSee('No hay solicitudes nuevas por revisar.');
});

it('enseña al docente sus próximas sesiones, también las que dicta en reemplazo, y no las de otros', function (): void {
    $docente = User::factory()->docente()->create();

    solicitudDelCaso('Atención de parto normal', ['docente_id' => $docente->id, 'fecha' => '2026-10-20'], 'aprobada');
    solicitudDelCaso('Sesión que ya pasó', ['docente_id' => $docente->id, 'fecha' => '2026-10-01'], 'aprobada');
    solicitudDelCaso('Crisis convulsiva', ['docente_que_dicta_id' => $docente->id, 'fecha' => '2026-10-15'], 'aprobada');
    solicitudDelCaso('Sesión de otro docente', ['fecha' => '2026-10-16'], 'aprobada');
    solicitudDelCaso('Herida por arma de fuego', ['docente_id' => $docente->id, 'fecha' => '2026-10-22']);

    $this->actingAs($docente)
        ->get(route('panel.inicio'))
        ->assertSeeInOrder(['Tus próximas sesiones', 'Crisis convulsiva', 'Atención de parto normal', 'Tus solicitudes en trámite', 'Herida por arma de fuego'])
        ->assertDontSee('Sesión que ya pasó')
        ->assertDontSee('Sesión de otro docente')
        ->assertDontSee('Por aprobar');
});

it('le recuerda a quien firma el estado de su formato del periodo', function (): void {
    abrirPeriodo();
    $docente = User::factory()->docente()->create();
    FormatoConfidencialidad::factory()->cargado()->create(['firmante_id' => $docente->id]);

    $this->actingAs($docente)->get(route('panel.inicio'))
        ->assertSee('Tu formato de confidencialidad')
        ->assertSee('En revisión')
        ->assertSee('Ya lo subiste.');

    $this->actingAs(User::factory()->estudiante()->create())->get(route('panel.inicio'))
        ->assertSee('Pendiente')
        ->assertSee('Sin él no puedes entrar a las prácticas.')
        ->assertSee('Ir a mi formato');
});

it('no le pide nada a quien ya tiene el formato verificado', function (): void {
    abrirPeriodo();
    $estudiante = User::factory()->estudiante()->create();
    FormatoConfidencialidad::factory()->verificado()->create(['firmante_id' => $estudiante->id]);

    $this->actingAs($estudiante)->get(route('panel.inicio'))
        ->assertSee('Al día')
        ->assertDontSee('Ir a mi formato');
});

it('no le recuerda el formato a quien lo verifica, que no lo firma', function (): void {
    $this->actingAs(User::factory()->administrativo()->create())
        ->get(route('panel.inicio'))
        ->assertDontSee('Tu formato de confidencialidad')
        ->assertSee('Formatos por verificar');
});

it('enseña a quien verifica cuántos formatos del periodo esperan', function (): void {
    abrirPeriodo();
    FormatoConfidencialidad::factory()->count(2)->cargado()->create();
    FormatoConfidencialidad::factory()->cargado()->delPeriodo('2026-1')->create();

    $this->actingAs(User::factory()->administrativo()->create())
        ->get(route('panel.inicio'))
        ->assertSee('formatos cargados esperan');

    expect(app(ResumenDelDiaService::class)->para(User::factory()->administrativo()->create(), now())->formatosPorVerificar)->toBe(2);
});

it('enseña al estudiante sus resultados publicados, no los que el docente sigue calificando', function (): void {
    $estudiante = User::factory()->estudiante()->create();
    $publicada = Evaluacion::factory()->finalizada()->create(['tipo_evaluacion_id' => TipoEvaluacion::factory()->create(['nombre' => 'Toma de signos vitales'])->id]);
    $enBorrador = Evaluacion::factory()->create(['tipo_evaluacion_id' => TipoEvaluacion::factory()->create(['nombre' => 'Curación de heridas'])->id]);
    EvaluacionEstudiante::factory()->create(['evaluacion_id' => $publicada->id, 'estudiante_id' => $estudiante->id]);
    EvaluacionEstudiante::factory()->create(['evaluacion_id' => $enBorrador->id, 'estudiante_id' => $estudiante->id]);

    $this->actingAs($estudiante)->get(route('panel.inicio'))
        ->assertSee('Tus últimos resultados')
        ->assertSee('Toma de signos vitales')
        ->assertSee('Aprobado')
        ->assertDontSee('Curación de heridas');
});

it('no genera consultas N+1 al pintar «Hoy»', function (): void {
    abrirPeriodo();
    Solicitud::factory()->count(3)->revisada()->create();
    Solicitud::factory()->count(3)->create();
    escenarioDeHoy('Reanimación neonatal');
    escenarioDeHoy('Crisis convulsiva');

    Model::preventLazyLoading();

    $this->actingAs(User::factory()->coordinador()->create())->get(route('panel.inicio'))->assertOk();
});
