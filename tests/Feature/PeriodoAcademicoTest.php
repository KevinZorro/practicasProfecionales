<?php

declare(strict_types=1);

use App\Enums\Rol;
use App\Exceptions\PeriodoAcademicoInvalido;
use App\Livewire\PeriodoAcademico\GestionDePeriodos;
use App\Models\PeriodoAcademico;
use App\Models\User;
use App\Services\PeriodoAcademicoService;
use Database\Seeders\RolSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->servicio = app(PeriodoAcademicoService::class);
    $this->administrativo = User::factory()->administrativo()->create();
});

// ---------------------------------------------------------------------
// Reglas (RF75)
// ---------------------------------------------------------------------

it('abre un periodo y deja rastro de quién y cuándo', function (): void {
    $periodo = $this->servicio->abrir(' 2026-2 ', $this->administrativo);

    expect($periodo->nombre)->toBe('2026-2')
        ->and($periodo->abierto_por)->toBe($this->administrativo->id)
        ->and($periodo->abierto_at)->not->toBeNull()
        ->and($this->servicio->abierto()?->is($periodo))->toBeTrue();
});

it('no deja abrir un periodo mientras otro sigue abierto', function (): void {
    $this->servicio->abrir('2026-2', $this->administrativo);

    expect(fn () => $this->servicio->abrir('2027-1', $this->administrativo))
        ->toThrow(PeriodoAcademicoInvalido::class, 'sigue abierto');
});

it('no deja dos periodos abiertos ni saltándose el Service', function (): void {
    // El índice único parcial lo garantiza en la base.
    PeriodoAcademico::factory()->create(['nombre' => '2026-2']);

    expect(fn () => PeriodoAcademico::factory()->create(['nombre' => '2027-1']))
        ->toThrow(QueryException::class);
});

it('no repite el nombre de un periodo', function (): void {
    $periodo = $this->servicio->abrir('2026-2', $this->administrativo);
    $this->servicio->cerrar($periodo, $this->administrativo);

    expect(fn () => $this->servicio->abrir('2026-2', $this->administrativo))
        ->toThrow(PeriodoAcademicoInvalido::class, 'Ya existe');
});

it('sigue rigiendo el último periodo cerrado hasta que se abra otro', function (): void {
    $anterior = $this->servicio->abrir('2026-1', $this->administrativo);
    $this->servicio->cerrar($anterior, $this->administrativo);
    $ultimo = $this->servicio->abrir('2026-2', $this->administrativo);
    $this->servicio->cerrar($ultimo, $this->administrativo);

    expect($this->servicio->abierto())->toBeNull()
        ->and($this->servicio->vigente()?->is($ultimo))->toBeTrue();
});

it('no cierra dos veces el mismo periodo', function (): void {
    $periodo = $this->servicio->abrir('2026-2', $this->administrativo);
    $this->servicio->cerrar($periodo, $this->administrativo);

    expect(fn () => $this->servicio->cerrar($periodo->fresh(), $this->administrativo))
        ->toThrow(PeriodoAcademicoInvalido::class, 'ya está cerrado');
});

it('reabre el último periodo cerrado para deshacer un cierre por error', function (): void {
    $periodo = $this->servicio->abrir('2026-2', $this->administrativo);
    $this->servicio->cerrar($periodo, $this->administrativo);

    $this->servicio->reabrir($periodo->fresh(), $this->administrativo);

    expect($periodo->fresh()->estaAbierto())->toBeTrue()
        ->and($periodo->fresh()->cerrado_por)->toBeNull();
});

it('no reabre un periodo que no es el último', function (): void {
    $viejo = $this->servicio->abrir('2026-1', $this->administrativo);
    $this->servicio->cerrar($viejo, $this->administrativo);
    $nuevo = $this->servicio->abrir('2026-2', $this->administrativo);
    $this->servicio->cerrar($nuevo, $this->administrativo);

    expect(fn () => $this->servicio->reabrir($viejo->fresh(), $this->administrativo))
        ->toThrow(PeriodoAcademicoInvalido::class, 'último');
});

it('no reabre un periodo si ya hay otro abierto', function (): void {
    $viejo = $this->servicio->abrir('2026-1', $this->administrativo);
    $this->servicio->cerrar($viejo, $this->administrativo);
    $this->servicio->abrir('2026-2', $this->administrativo);

    expect(fn () => $this->servicio->reabrir($viejo->fresh(), $this->administrativo))
        ->toThrow(PeriodoAcademicoInvalido::class, 'sigue abierto');
});

// ---------------------------------------------------------------------
// Permisos
// ---------------------------------------------------------------------

it('deja gestionar el periodo al administrativo, a coordinación y al ADMIN', function (Rol $rol, bool $puede): void {
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);

    expect($usuario->can('viewAny', PeriodoAcademico::class))->toBe($puede)
        ->and($usuario->can('create', PeriodoAcademico::class))->toBe($puede);
})->with([
    'admin' => [Rol::Admin, true],
    'coordinador' => [Rol::Coordinador, true],
    'administrativo' => [Rol::Administrativo, true],
    'docente' => [Rol::Docente, false],
    'estudiante' => [Rol::Estudiante, false],
]);

it('no deja a un docente abrir un periodo ni llamando al Service', function (): void {
    expect(fn () => $this->servicio->abrir('2026-2', User::factory()->docente()->create()))
        ->toThrow(AuthorizationException::class);
});

it('no deja entrar a la pantalla a un docente', function (): void {
    $this->actingAs(User::factory()->docente()->create())
        ->get(route('panel.periodo-academico'))
        ->assertForbidden();
});

// ---------------------------------------------------------------------
// Pantalla
// ---------------------------------------------------------------------

it('abre el periodo desde la pantalla', function (): void {
    Livewire::actingAs($this->administrativo)
        ->test(GestionDePeriodos::class)
        ->assertSee('Todavía no se ha abierto ningún periodo')
        ->set('nombre', '2026-2')
        ->call('abrir')
        ->assertHasNoErrors()
        ->assertSee('Periodo abierto')
        ->assertSee('Cerrar el periodo');

    expect(PeriodoAcademico::query()->abiertos()->value('nombre'))->toBe('2026-2');
});

it('exige el nombre del periodo', function (): void {
    Livewire::actingAs($this->administrativo)
        ->test(GestionDePeriodos::class)
        ->call('abrir')
        ->assertHasErrors(['nombre' => 'required']);
});

it('explica en pantalla por qué no se puede abrir un nombre repetido', function (): void {
    PeriodoAcademico::factory()->cerrado()->create(['nombre' => '2026-2']);

    Livewire::actingAs($this->administrativo)
        ->test(GestionDePeriodos::class)
        ->assertSee('Sigue valiendo')
        ->set('nombre', '2026-2')
        ->call('abrir')
        ->assertSee('Ya existe un periodo llamado 2026-2.');
});

it('cierra el periodo y ofrece reabrirlo', function (): void {
    $periodo = PeriodoAcademico::factory()->create(['nombre' => '2026-2']);

    Livewire::actingAs($this->administrativo)
        ->test(GestionDePeriodos::class)
        ->call('cerrar', $periodo->id)
        ->assertSee('Abrir el periodo')
        ->assertSee('Reabrir');

    expect($periodo->fresh()->cerrado_por)->toBe($this->administrativo->id);
});
