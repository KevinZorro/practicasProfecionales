<?php

declare(strict_types=1);

use App\Enums\EstadoSolicitud;
use App\Enums\Rol;
use App\Enums\TipoSesion;
use App\Models\CasoClinico;
use App\Models\Preparacion;
use App\Models\Sala;
use App\Models\Solicitud;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Database\Eloquent\Model;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
});

afterEach(function (): void {
    Model::preventLazyLoading(false);
});

/** Un usuario con el rol dado. */
function conRolParaCalendario(Rol $rol): User
{
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);

    return $usuario->fresh();
}

it('deja ver el calendario a los cinco roles', function (Rol $rol): void {
    $this->actingAs(conRolParaCalendario($rol))->get(route('panel.calendario'))
        ->assertOk()
        ->assertSee('Calendario de escenarios');
})->with([Rol::Admin, Rol::Coordinador, Rol::Administrativo, Rol::Docente, Rol::Estudiante]);

it('entrega solo los escenarios aprobados del rango', function (): void {
    Solicitud::factory()->aprobada()->create(['fecha' => '2026-10-05']);
    Solicitud::factory()->create(['fecha' => '2026-10-06', 'estado' => EstadoSolicitud::Pendiente]);
    Solicitud::factory()->create(['fecha' => '2026-10-07', 'estado' => EstadoSolicitud::Rechazada]);
    Solicitud::factory()->aprobada()->create(['fecha' => '2026-12-20']);

    $respuesta = $this->actingAs(conRolParaCalendario(Rol::Docente))
        ->getJson(route('panel.calendario.eventos', ['desde' => '2026-10-01', 'hasta' => '2026-10-31']));

    $respuesta->assertOk();
    expect($respuesta->json())->toHaveCount(1);
});

it('distingue la práctica de la evaluación por clase y por color', function (): void {
    Solicitud::factory()->aprobada()->create(['fecha' => '2026-10-05', 'tipo' => TipoSesion::Practica]);
    Solicitud::factory()->aprobada()->create(['fecha' => '2026-10-06', 'tipo' => TipoSesion::Evaluacion]);

    $eventos = collect($this->actingAs(conRolParaCalendario(Rol::Estudiante))
        ->getJson(route('panel.calendario.eventos', ['desde' => '2026-10-01', 'hasta' => '2026-10-31']))
        ->json());

    $practica = $eventos->firstWhere('extendedProps.tipo', 'Práctica');
    $evaluacion = $eventos->firstWhere('extendedProps.tipo', 'Evaluación');

    expect($practica['classNames'])->toBe(['evento-practica'])
        ->and($evaluacion['classNames'])->toBe(['evento-evaluacion'])
        ->and($practica['backgroundColor'])->not->toBe($evaluacion['backgroundColor']);
});

it('lleva en cada evento la hora, el caso clínico y el docente', function (): void {
    $docente = User::factory()->docente()->create(['nombre' => 'Ana Gómez']);
    $caso = CasoClinico::factory()->create(['nombre' => 'Atención de parto']);
    Solicitud::factory()->aprobada()->create([
        'docente_id' => $docente->id,
        'caso_clinico_id' => $caso->id,
        'fecha' => '2026-10-05',
        'hora_inicio' => '07:00:00',
        'hora_fin' => '09:30:00',
    ]);

    $evento = $this->actingAs(conRolParaCalendario(Rol::Docente))
        ->getJson(route('panel.calendario.eventos', ['desde' => '2026-10-01', 'hasta' => '2026-10-31']))
        ->json()[0];

    expect($evento['title'])->toBe('Atención de parto')
        ->and($evento['start'])->toBe('2026-10-05T07:00:00')
        ->and($evento['extendedProps']['hora'])->toBe('07:00–09:30')
        ->and($evento['extendedProps']['docente'])->toBe('Ana Gómez');
});

it('muestra la sala solo cuando ya está asignada', function (): void {
    $sinSala = Solicitud::factory()->aprobada()->create(['fecha' => '2026-10-05']);
    Preparacion::factory()->create(['solicitud_id' => $sinSala->id, 'sala_id' => null]);

    $conSala = Solicitud::factory()->aprobada()->create(['fecha' => '2026-10-06']);
    Preparacion::factory()->create([
        'solicitud_id' => $conSala->id,
        'sala_id' => Sala::factory()->create(['nombre' => 'Sala 1'])->id,
    ]);

    $eventos = collect($this->actingAs(conRolParaCalendario(Rol::Docente))
        ->getJson(route('panel.calendario.eventos', ['desde' => '2026-10-01', 'hasta' => '2026-10-31']))
        ->json())->keyBy('id');

    expect($eventos[(string) $sinSala->id]['extendedProps']['sala'])->toBe('Aún sin asignar')
        ->and($eventos[(string) $conSala->id]['extendedProps']['sala'])->toBe('Sala 1');
});

it('exige el rango de fechas', function (): void {
    $this->actingAs(conRolParaCalendario(Rol::Docente))
        ->getJson(route('panel.calendario.eventos'))
        ->assertJsonValidationErrors(['desde', 'hasta']);
});

it('no genera consultas N+1 al armar los eventos', function (): void {
    Solicitud::factory()->count(6)->aprobada()->create(['fecha' => '2026-10-05'])
        ->each(fn (Solicitud $s) => Preparacion::factory()->conSala()->create(['solicitud_id' => $s->id]));

    Model::preventLazyLoading();

    $this->actingAs(conRolParaCalendario(Rol::Docente))
        ->getJson(route('panel.calendario.eventos', ['desde' => '2026-10-01', 'hasta' => '2026-10-31']))
        ->assertOk()
        ->assertJsonCount(6);
});

it('no deja a un invitado pedir los eventos', function (): void {
    $this->getJson(route('panel.calendario.eventos', ['desde' => '2026-10-01', 'hasta' => '2026-10-31']))
        ->assertUnauthorized();
});
