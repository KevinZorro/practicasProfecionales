<?php

declare(strict_types=1);

use App\Enums\ImpedimentoDeIngreso;
use App\Enums\Rol;
use App\Exceptions\BloqueoInvalido;
use App\Exceptions\SolicitudInvalida;
use App\Livewire\Bloqueo\GestionDeBloqueos;
use App\Livewire\Solicitud\FormularioSolicitud;
use App\Models\Bloqueo;
use App\Models\FormatoConfidencialidad;
use App\Models\User;
use App\Services\BloqueoService;
use App\Services\ParticipacionService;
use App\Services\SolicitudService;
use Database\Seeders\RolSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

/*
 * Bloqueo de estudiantes y docentes para el uso del laboratorio (RF68), y
 * qué impide entrar (RF70).
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    abrirPeriodo();
    $this->servicio = app(BloqueoService::class);
    $this->coordinadora = User::factory()->coordinador()->create();
});

it('bloquea a un estudiante con motivo y deja rastro de quién', function (): void {
    $estudiante = User::factory()->estudiante()->create();

    $bloqueo = $this->servicio->bloquear($estudiante, ' Daño de un simulador ', $this->coordinadora);

    expect($bloqueo->motivo)->toBe('Daño de un simulador')
        ->and($bloqueo->bloqueado_por)->toBe($this->coordinadora->id)
        ->and($this->servicio->vigenteDe($estudiante)?->is($bloqueo))->toBeTrue();
});

it('bloquea también a un docente', function (): void {
    $docente = User::factory()->docente()->create();

    expect($this->servicio->bloquear($docente, 'Incumplimiento del reglamento', $this->coordinadora)->user_id)
        ->toBe($docente->id);
});

it('no bloquea a quien no es estudiante ni docente', function (): void {
    expect(fn () => $this->servicio->bloquear(User::factory()->administrativo()->create(), 'X', $this->coordinadora))
        ->toThrow(BloqueoInvalido::class, 'Solo se bloquea');
});

it('exige el motivo para bloquear y para levantar', function (): void {
    $estudiante = User::factory()->estudiante()->create();

    expect(fn () => $this->servicio->bloquear($estudiante, '   ', $this->coordinadora))
        ->toThrow(BloqueoInvalido::class, 'motivo');

    $bloqueo = $this->servicio->bloquear($estudiante, 'Motivo', $this->coordinadora);

    expect(fn () => $this->servicio->levantar($bloqueo, '', $this->coordinadora))
        ->toThrow(BloqueoInvalido::class, 'motivo');
});

it('no bloquea dos veces a la misma persona, ni saltándose el Service', function (): void {
    $estudiante = User::factory()->estudiante()->create();
    $this->servicio->bloquear($estudiante, 'Primero', $this->coordinadora);

    expect(fn () => $this->servicio->bloquear($estudiante, 'Segundo', $this->coordinadora))
        ->toThrow(BloqueoInvalido::class, 'ya tiene un bloqueo vigente');

    expect(fn () => Bloqueo::factory()->create(['user_id' => $estudiante->id]))
        ->toThrow(QueryException::class);
});

it('levanta el bloqueo sin borrarlo, y permite volver a bloquear después', function (): void {
    $estudiante = User::factory()->estudiante()->create();
    $bloqueo = $this->servicio->bloquear($estudiante, 'Primero', $this->coordinadora);

    $this->servicio->levantar($bloqueo, 'Cumplió la sanción', $this->coordinadora);

    expect($bloqueo->fresh()->levantado_por)->toBe($this->coordinadora->id)
        ->and($bloqueo->fresh()->motivo_levantamiento)->toBe('Cumplió la sanción')
        ->and($this->servicio->vigenteDe($estudiante))->toBeNull()
        ->and(fn () => $this->servicio->levantar($bloqueo->fresh(), 'Otra vez', $this->coordinadora))->toThrow(BloqueoInvalido::class);

    $this->servicio->bloquear($estudiante, 'Reincidió', $this->coordinadora);

    expect(Bloqueo::query()->where('user_id', $estudiante->id)->count())->toBe(2);
});

it('reserva el bloqueo a coordinación y al ADMIN', function (Rol $rol, bool $puede): void {
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);

    expect($usuario->can('create', Bloqueo::class))->toBe($puede);
})->with([
    'admin' => [Rol::Admin, true],
    'coordinador' => [Rol::Coordinador, true],
    'administrativo' => [Rol::Administrativo, false],
    'docente' => [Rol::Docente, false],
    'estudiante' => [Rol::Estudiante, false],
]);

it('no deja bloquear a un administrativo ni llamando al Service', function (): void {
    expect(fn () => $this->servicio->bloquear(User::factory()->estudiante()->create(), 'X', User::factory()->administrativo()->create()))
        ->toThrow(AuthorizationException::class);
});

// ---------------------------------------------------------------------
// Qué impide entrar (RF70)
// ---------------------------------------------------------------------

it('dice por qué alguien no puede entrar: sin formato, bloqueado o ambas', function (): void {
    $participacion = app(ParticipacionService::class);
    $habilitado = User::factory()->estudiante()->habilitado()->create();
    $sinFormato = User::factory()->estudiante()->create();
    $bloqueado = User::factory()->estudiante()->habilitado()->create();
    $ambas = User::factory()->estudiante()->create();
    $this->servicio->bloquear($bloqueado, 'Daño de un simulador', $this->coordinadora);
    $this->servicio->bloquear($ambas, 'Reglamento', $this->coordinadora);

    $impedimentos = $participacion->impedimentos([$habilitado->id, $sinFormato->id, $bloqueado->id, $ambas->id]);
    $tipos = static fn (array $lista): array => array_map(static fn ($i) => $i->tipo, $lista);

    expect($impedimentos[$habilitado->id])->toBe([])
        ->and($tipos($impedimentos[$sinFormato->id]))->toBe([ImpedimentoDeIngreso::SinFormato])
        ->and($tipos($impedimentos[$bloqueado->id]))->toBe([ImpedimentoDeIngreso::Bloqueado])
        ->and($impedimentos[$bloqueado->id][0]->descripcion())->toBe('Bloqueado por coordinación: Daño de un simulador')
        ->and($tipos($impedimentos[$ambas->id]))->toBe([ImpedimentoDeIngreso::SinFormato, ImpedimentoDeIngreso::Bloqueado])
        ->and($participacion->puedeIngresar($habilitado))->toBeTrue();
});

it('habilita a quien entregó el formato en físico aunque no lo haya escaneado', function (): void {
    $estudiante = User::factory()->estudiante()->create();
    FormatoConfidencialidad::factory()->entregadoEnFisico()->create(['firmante_id' => $estudiante->id]);

    expect(app(ParticipacionService::class)->puedeIngresar($estudiante))->toBeTrue();
});

// ---------------------------------------------------------------------
// Docente bloqueado (D2)
// ---------------------------------------------------------------------

it('no deja a un docente bloqueado pedir escenarios', function (): void {
    $docente = User::factory()->docente()->create();
    $this->servicio->bloquear($docente, 'Reglamento', $this->coordinadora);

    expect(fn () => app(SolicitudService::class)->crear($docente, sesionCon(idsDeEstudiantes(1))))
        ->toThrow(SolicitudInvalida::class, 'bloqueo vigente');

    Livewire::actingAs($docente)
        ->test(FormularioSolicitud::class)
        ->assertSee('No puedes solicitar escenarios mientras tengas un bloqueo vigente.')
        ->assertDontSee('Enviar solicitud');
});

// ---------------------------------------------------------------------
// Pantalla
// ---------------------------------------------------------------------

it('bloquea desde la pantalla buscando a la persona', function (): void {
    $estudiante = User::factory()->estudiante()->create(['nombre' => 'Ana Ruiz']);

    Livewire::actingAs($this->coordinadora)
        ->test(GestionDeBloqueos::class)
        ->set('busquedaPersona', 'Ana')
        ->assertSee('Ana Ruiz')
        ->call('elegir', $estudiante->id)
        ->set('motivo', 'Daño de un simulador')
        ->call('bloquear')
        ->assertHasNoErrors()
        ->assertSee('Daño de un simulador');

    expect($this->servicio->vigenteDe($estudiante))->not->toBeNull();
});

it('levanta un bloqueo desde la pantalla con motivo', function (): void {
    $bloqueo = $this->servicio->bloquear(User::factory()->estudiante()->create(), 'Reglamento', $this->coordinadora);

    Livewire::actingAs($this->coordinadora)
        ->test(GestionDeBloqueos::class)
        ->call('pedirMotivo', $bloqueo->id)
        ->call('levantar', $bloqueo->id)
        ->assertHasErrors(['motivoLevantamiento' => 'required'])
        ->set('motivoLevantamiento', 'Cumplió la sanción')
        ->call('levantar', $bloqueo->id)
        ->assertHasNoErrors();

    expect($bloqueo->fresh()->estaVigente())->toBeFalse();
});

it('no deja entrar a la pantalla de bloqueos a un administrativo', function (): void {
    $this->actingAs(User::factory()->administrativo()->create())
        ->get(route('panel.bloqueos'))
        ->assertForbidden();
});
