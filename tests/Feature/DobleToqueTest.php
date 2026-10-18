<?php

declare(strict_types=1);

use App\Enums\EstadoSolicitud;
use App\Exceptions\SolicitudInvalida;
use App\Livewire\Evaluacion\RegistroDeEvaluacion;
use App\Livewire\Solicitud\ExpedienteDeSolicitud;
use App\Models\Evaluacion;
use App\Models\ItemChecklist;
use App\Models\Materia;
use App\Models\Solicitud;
use App\Models\TipoEvaluacion;
use App\Models\User;
use App\Services\EvaluacionService;
use App\Services\SolicitudService;
use Database\Seeders\RolSeeder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/*
 * QA-04: el administrativo trabaja desde el celular y el doble toque es
 * frecuente. Un segundo toque no puede terminar en una ventana de error.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    abrirPeriodo();
});

it('el botón se deshabilita mientras Livewire procesa', function (): void {
    $html = Blade::render('<x-boton wire:click="algo">Guardar</x-boton>');

    expect($html)->toContain('wire:loading.attr="disabled"');
});

it('el enlace con aspecto de botón no lleva el atributo de carga', function (): void {
    $html = Blade::render('<x-boton href="/panel">Ir</x-boton>');

    expect($html)->not->toContain('wire:loading');
});

it('un segundo toque en «Marcar como revisada» no falla ni repite la revisión', function (): void {
    $administrativo = User::factory()->administrativo()->create();
    $solicitud = Solicitud::factory()->create();

    $pantalla = Livewire::actingAs($administrativo)->test(ExpedienteDeSolicitud::class, ['solicitud' => $solicitud]);
    $pantalla->call('revisar')->assertHasNoErrors();
    $revisadaAt = $solicitud->fresh()->revisada_at;

    $pantalla->call('revisar')->assertHasNoErrors();

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Revisada)
        ->and($solicitud->fresh()->revisada_por)->toBe($administrativo->id)
        ->and($solicitud->fresh()->revisada_at->equalTo($revisadaAt))->toBeTrue();
});

it('si otra persona rechazó mientras tanto, aprobar responde 403 y no cambia nada', function (): void {
    $coordinadora = User::factory()->coordinador()->create();
    $solicitud = Solicitud::factory()->revisada()->create();
    $pantalla = Livewire::actingAs($coordinadora)->test(ExpedienteDeSolicitud::class, ['solicitud' => $solicitud]);

    // Otra persona rechaza antes de que llegue el toque de esta pantalla.
    app(SolicitudService::class)->rechazar($solicitud->fresh(), User::factory()->admin()->create());

    // La Policy ya no deja aprobar lo que no está revisado: 403, no 500.
    $pantalla->call('aprobar')->assertForbidden();
    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Rechazada);
});

/** Una evaluación en borrador con un estudiante de la lista de la sesión. */
function evaluacionConEstudianteDeLaLista(User $docente, User $estudiante): Evaluacion
{
    $materia = Materia::factory()->create();
    $solicitud = Solicitud::factory()->deEvaluacion()->aprobada()->create([
        'materia_id' => $materia->id,
        'docente_id' => $docente->id,
        'fecha' => now()->addDay()->format('Y-m-d'),
    ]);
    $solicitud->estudiantes()->attach($estudiante->id);
    $tipo = TipoEvaluacion::factory()->create();
    $tipo->materias()->attach($materia->id);
    ItemChecklist::factory()->create(['tipo_evaluacion_id' => $tipo->id, 'orden' => 1]);

    return app(EvaluacionService::class)->crear($solicitud, $tipo, $docente);
}

it('un segundo toque en «Agregar» no duplica al estudiante ni falla', function (): void {
    $docente = User::factory()->docente()->create();
    $estudiante = User::factory()->estudiante()->habilitado()->create();
    $evaluacion = evaluacionConEstudianteDeLaLista($docente, $estudiante);

    Livewire::actingAs($docente)
        ->test(RegistroDeEvaluacion::class, ['evaluacion' => $evaluacion])
        ->call('agregar', $estudiante->id)
        ->call('agregar', $estudiante->id)
        ->assertSet('errorDeRegla', null);

    expect($evaluacion->estudiantes()->count())->toBe(1);
});

it('el servicio agrega al mismo estudiante dos veces sin duplicarlo', function (): void {
    $docente = User::factory()->docente()->create();
    $estudiante = User::factory()->estudiante()->habilitado()->create();
    $evaluacion = evaluacionConEstudianteDeLaLista($docente, $estudiante);
    $servicio = app(EvaluacionService::class);

    $primero = $servicio->agregarEstudiante($evaluacion, $estudiante);
    $segundo = $servicio->agregarEstudiante($evaluacion->fresh(), $estudiante);

    expect($segundo->id)->toBe($primero->id)
        ->and($evaluacion->estudiantes()->count())->toBe(1);
});

// ---------------------------------------------------------------------
// Excepciones de dominio: nunca un 500
// ---------------------------------------------------------------------

it('una regla de dominio rota responde 422 con su mensaje, no 500', function (): void {
    Route::middleware('web')->get('/_prueba-regla', static function (): never {
        throw SolicitudInvalida::fechaPasada();
    });

    $this->getJson('/_prueba-regla')
        ->assertStatus(422)
        ->assertJson(['message' => 'Ese día ya pasó: la sesión tiene que ser de hoy en adelante.']);

    $this->get('/_prueba-regla')
        ->assertStatus(422)
        ->assertSee('Ese día ya pasó', false);
});
