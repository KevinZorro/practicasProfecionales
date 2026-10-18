<?php

declare(strict_types=1);

use App\Livewire\Evaluacion\Evaluaciones;
use App\Livewire\Solicitud\NovedadesDeLaSesion;
use App\Models\Evaluacion;
use App\Models\Preparacion;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\BloqueoService;
use App\Services\NovedadesDeSesionService;
use Database\Seeders\RolSeeder;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

/*
 * QA-22: cuatro puntos de robustez que no tenían dueño.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    abrirPeriodo();
});

// ---------------------------------------------------------------------
// El calendario no entrega toda la historia
// ---------------------------------------------------------------------

it('el calendario rechaza un rango de años, pero acepta el que pide FullCalendar', function (): void {
    $coordinadora = User::factory()->coordinador()->create();
    Solicitud::factory()->aprobada()->create(['fecha' => '2026-10-05']);

    $this->actingAs($coordinadora)
        ->getJson(route('panel.calendario.eventos', ['desde' => '2000-01-01', 'hasta' => '2100-12-31']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['hasta']);

    // Una vista mensual con los días de las semanas vecinas son 42 días.
    $this->actingAs($coordinadora)
        ->getJson(route('panel.calendario.eventos', ['desde' => '2026-09-28', 'hasta' => '2026-11-08']))
        ->assertOk()
        ->assertJsonCount(1);
});

// ---------------------------------------------------------------------
// Un solo correo, sin importar las mayúsculas
// ---------------------------------------------------------------------

it('no admite dos cuentas cuyo correo solo difiere en mayúsculas', function (): void {
    User::factory()->create(['email' => 'persona@ufps.edu.co']);

    expect(fn () => User::factory()->create(['email' => 'Persona@UFPS.edu.co']))
        ->toThrow(QueryException::class);
});

// ---------------------------------------------------------------------
// Sustitución: avisos de bloqueo y de cruce (RF68)
// ---------------------------------------------------------------------

function sesionConReemplazoPosible(): array
{
    $titular = User::factory()->docente()->create();
    $reemplazo = User::factory()->docente()->create(['nombre' => 'Diana Mejía']);
    $sesion = Solicitud::factory()->aprobada()->create([
        'docente_id' => $titular->id,
        'fecha' => now()->addDays(3)->format('Y-m-d'),
        'hora_inicio' => '08:00:00',
        'hora_fin' => '10:00:00',
    ]);
    Preparacion::factory()->create(['solicitud_id' => $sesion->id]);

    return [$sesion, $reemplazo];
}

it('avisa si el reemplazo está bloqueado o ya dicta otra sesión a esa hora, sin impedir la sustitución', function (): void {
    [$sesion, $reemplazo] = sesionConReemplazoPosible();
    $coordinadora = User::factory()->coordinador()->create();
    $servicio = app(NovedadesDeSesionService::class);

    expect($servicio->avisosDeSustitucion($sesion, $reemplazo))->toBe([]);

    app(BloqueoService::class)->bloquear($reemplazo, 'Reglamento', $coordinadora);
    Solicitud::factory()->aprobada()->create([
        'docente_id' => $reemplazo->id,
        'fecha' => $sesion->fecha->format('Y-m-d'),
        'hora_inicio' => '09:00:00',
        'hora_fin' => '11:00:00',
    ]);

    $avisos = $servicio->avisosDeSustitucion($sesion->fresh(), $reemplazo);

    expect($avisos)->toHaveCount(2)
        ->and(implode(' ', $avisos))->toContain('bloqueado')->toContain('Reglamento')->toContain('otra sesión');

    // Es un aviso, no un impedimento: la sustitución se registra igual.
    $servicio->sustituir($sesion->fresh(), $reemplazo, 'Incapacidad', $coordinadora);
    expect($sesion->fresh()->docente_que_dicta_id)->toBe($reemplazo->id);
});

it('la pantalla enseña esos avisos al elegir al reemplazo', function (): void {
    [$sesion, $reemplazo] = sesionConReemplazoPosible();
    $administrativo = User::factory()->administrativo()->create();
    app(BloqueoService::class)->bloquear($reemplazo, 'Reglamento', User::factory()->coordinador()->create());

    Livewire::actingAs($administrativo)
        ->test(NovedadesDeLaSesion::class, ['solicitud' => $sesion])
        ->assertDontSee('Diana Mejía está bloqueado')
        ->set('reemplazoId', $reemplazo->id)
        ->assertSee('Diana Mejía está bloqueado');
});

// ---------------------------------------------------------------------
// Registrar la evaluación: la decisión es de una Policy
// ---------------------------------------------------------------------

it('la Policy dice quién registra la evaluación de una sesión: quien la dicta', function (): void {
    [$sesion, $reemplazo] = sesionConReemplazoPosible();
    $titular = $sesion->docente;
    $otro = User::factory()->docente()->create();

    expect($titular->can('registrarEnSesion', [Evaluacion::class, $sesion]))->toBeTrue()
        ->and($reemplazo->can('registrarEnSesion', [Evaluacion::class, $sesion]))->toBeFalse()
        ->and($otro->can('registrarEnSesion', [Evaluacion::class, $sesion]))->toBeFalse();

    $sesion->update(['docente_que_dicta_id' => $reemplazo->id]);

    expect($reemplazo->can('registrarEnSesion', [Evaluacion::class, $sesion->fresh()]))->toBeTrue()
        ->and($titular->can('registrarEnSesion', [Evaluacion::class, $sesion->fresh()]))->toBeFalse();
});

it('un coordinador que no dicta la sesión tampoco la registra', function (): void {
    [$sesion] = sesionConReemplazoPosible();
    $coordinador = User::factory()->coordinador()->create();

    expect($coordinador->can('registrarEnSesion', [Evaluacion::class, $sesion]))->toBeFalse();
});

it('la pantalla usa esa Policy y responde 403 a quien no dicta', function (): void {
    [$sesion] = sesionConReemplazoPosible();

    Livewire::actingAs(User::factory()->docente()->create())
        ->test(Evaluaciones::class)
        ->call('registrar', $sesion->id)
        ->assertForbidden();
});
