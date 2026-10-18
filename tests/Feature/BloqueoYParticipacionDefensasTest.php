<?php

declare(strict_types=1);

use App\Enums\ImpedimentoDeIngreso;
use App\Models\Bloqueo;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\BloqueoService;
use App\Services\ParticipacionService;
use Database\Seeders\RolSeeder;
use Illuminate\Auth\Access\AuthorizationException;

/*
 * QA-16: líneas que el análisis de mutación (Pest mutate) encontró sin
 * vigilancia. El código estaba bien; faltaba una prueba que avisara si
 * alguien lo rompe.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    abrirPeriodo();
    $this->servicio = app(BloqueoService::class);
    $this->coordinadora = User::factory()->coordinador()->create();
});

// ---------------------------------------------------------------------
// BloqueoService::levantar — permiso en el Service
// ---------------------------------------------------------------------

it('levantar vuelve a comprobar el permiso aunque llegue de un comando o una cola', function (string $quien): void {
    $bloqueo = $this->servicio->bloquear(User::factory()->estudiante()->create(), 'Daño', $this->coordinadora);
    $actor = User::factory()->{$quien}()->create();

    expect(fn () => $this->servicio->levantar($bloqueo, 'Ya pagó el daño', $actor))
        ->toThrow(AuthorizationException::class);
    expect($bloqueo->fresh()->estaVigente())->toBeTrue();
})->with(['administrativo', 'docente', 'estudiante']);

it('levantar deja levantar a coordinación', function (): void {
    $bloqueo = $this->servicio->bloquear(User::factory()->estudiante()->create(), 'Daño', $this->coordinadora);

    expect($this->servicio->levantar($bloqueo, 'Ya pagó el daño', $this->coordinadora)->estaVigente())->toBeFalse();
});

// ---------------------------------------------------------------------
// BloqueoService::listado y bloqueables — búsqueda y filtros
// ---------------------------------------------------------------------

it('el listado busca por nombre, correo y código, y distingue vigentes de levantados', function (): void {
    $ana = User::factory()->estudiante()->create(['nombre' => 'Ana Prueba', 'email' => 'ana.p@ufps.edu.co', 'codigo_institucional' => '1152001']);
    $beto = User::factory()->estudiante()->create(['nombre' => 'Beto Otro', 'email' => 'beto.o@ufps.edu.co', 'codigo_institucional' => '1152002']);
    $this->servicio->bloquear($ana, 'Motivo uno', $this->coordinadora);
    $levantado = $this->servicio->bloquear($beto, 'Motivo dos', $this->coordinadora);
    $this->servicio->levantar($levantado, 'Resuelto', $this->coordinadora);

    $nombres = static fn (?string $busqueda, bool $vigentes) => app(BloqueoService::class)->listado($busqueda, $vigentes)->getCollection()->map(fn (Bloqueo $b) => $b->persona->nombre)->all();

    expect($nombres(null, true))->toBe(['Ana Prueba'])
        ->and($nombres(null, false))->toEqualCanonicalizing(['Ana Prueba', 'Beto Otro'])
        ->and($nombres('ana.p@ufps', false))->toBe(['Ana Prueba'])
        ->and($nombres('1152002', false))->toBe(['Beto Otro'])
        ->and($nombres('BETO', false))->toBe(['Beto Otro'])
        ->and($nombres('beto', true))->toBe([])
        ->and($nombres('   ', true))->toBe(['Ana Prueba']);
});

it('el listado enseña primero los vigentes y después el historial', function (): void {
    $viejo = $this->servicio->bloquear(User::factory()->estudiante()->create(['nombre' => 'Primero Levantado']), 'a', $this->coordinadora);
    $this->servicio->levantar($viejo, 'b', $this->coordinadora);
    $this->servicio->bloquear(User::factory()->estudiante()->create(['nombre' => 'Despues Vigente']), 'c', $this->coordinadora);

    $orden = $this->servicio->listado(null, false)->getCollection()->map(fn (Bloqueo $b) => $b->persona->nombre)->all();

    expect($orden)->toBe(['Despues Vigente', 'Primero Levantado']);
});

it('ofrece para bloquear a quien firma el formato y no está bloqueado, por nombre, correo o código', function (): void {
    $libre = User::factory()->estudiante()->create(['nombre' => 'Lucia Libre', 'email' => 'lucia@ufps.edu.co', 'codigo_institucional' => '1153001']);
    $bloqueada = User::factory()->estudiante()->create(['nombre' => 'Lucas Libre', 'codigo_institucional' => '1153002']);
    User::factory()->administrativo()->create(['nombre' => 'Lupe Libre']);
    $this->servicio->bloquear($bloqueada, 'Daño', $this->coordinadora);

    $ofrecidos = static fn (string $q) => app(BloqueoService::class)->bloqueables($q)->pluck('nombre')->all();

    expect($ofrecidos('libre'))->toBe(['Lucia Libre'])
        ->and($ofrecidos('lucia@'))->toBe(['Lucia Libre'])
        ->and($ofrecidos('1153001'))->toBe(['Lucia Libre'])
        ->and($ofrecidos('   '))->toBe([])
        ->and($libre->exists)->toBeTrue();
});

// ---------------------------------------------------------------------
// ParticipacionService::deLaSesion — quien dicta es quien entra (RF73)
// ---------------------------------------------------------------------

it('la lista de la sesión revisa al docente que dicta, no al titular sustituido', function (): void {
    $titular = User::factory()->docente()->create(['nombre' => 'Titular']);
    $reemplazo = User::factory()->docente()->create(['nombre' => 'Reemplazo']);
    $sesion = Solicitud::factory()->aprobada()->create(['docente_id' => $titular->id, 'docente_que_dicta_id' => $reemplazo->id]);

    // Se bloquea al titular: ya no dicta, así que no cuenta.
    $this->servicio->bloquear($titular, 'Reglamento', $this->coordinadora);

    $lista = app(ParticipacionService::class)->deLaSesion($sesion->fresh());

    $tipos = static fn (array $impedimentos): array => array_map(static fn ($impedimento) => $impedimento->tipo, $impedimentos);

    expect($lista['docente']->is($reemplazo))->toBeTrue()
        ->and($tipos($lista['impedimentosDelDocente']))->not->toContain(ImpedimentoDeIngreso::Bloqueado);

    // Y si se bloquea al reemplazo, sí.
    $this->servicio->bloquear($reemplazo, 'Reglamento', $this->coordinadora);
    $lista = app(ParticipacionService::class)->deLaSesion($sesion->fresh());

    expect($tipos($lista['impedimentosDelDocente']))->toContain(ImpedimentoDeIngreso::Bloqueado);
});
