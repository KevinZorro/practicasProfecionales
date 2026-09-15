<?php

declare(strict_types=1);

use App\Enums\EstadoPreparacion;
use App\Enums\EstadoSolicitud;
use App\Enums\Rol;
use App\Enums\TipoSesion;
use App\Livewire\Preparacion\TableroDiario;
use App\Models\CasoClinico;
use App\Models\ItemInventario;
use App\Models\Preparacion;
use App\Models\Sala;
use App\Models\Solicitud;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->administrativo = User::factory()->administrativo()->create();
    $this->coordinadora = User::factory()->coordinador()->create();
});

afterEach(function (): void {
    Model::preventLazyLoading(false);
});

/** Un escenario aprobado con su preparación pendiente, como lo deja SolicitudService. */
function montajeParaPantalla(
    string $fecha = '2026-10-05',
    string $inicio = '07:00:00',
    string $fin = '09:00:00',
    EstadoPreparacion $estado = EstadoPreparacion::Pendiente,
    ?Sala $sala = null,
    TipoSesion $tipo = TipoSesion::Practica,
): Preparacion {
    $solicitud = Solicitud::factory()->aprobada()->create([
        'fecha' => $fecha,
        'hora_inicio' => $inicio,
        'hora_fin' => $fin,
        'tipo' => $tipo,
    ]);

    return Preparacion::factory()->create([
        'solicitud_id' => $solicitud->id,
        'sala_id' => $sala?->id,
        'estado' => $estado,
    ]);
}

// ---------------------------------------------------------------------
// Tablero
// ---------------------------------------------------------------------

it('arranca en la fecha de hoy', function (): void {
    Livewire::actingAs($this->administrativo)
        ->test(TableroDiario::class)
        ->assertSet('fecha', now()->toDateString());
});

it('muestra solo los escenarios de la fecha elegida', function (): void {
    montajeParaPantalla('2026-10-05');
    montajeParaPantalla('2026-10-06');

    Livewire::actingAs($this->administrativo)
        ->test(TableroDiario::class)
        ->set('fecha', '2026-10-05')
        ->assertViewHas('montajes', fn ($m): bool => $m->count() === 1);
});

it('no muestra escenarios de solicitudes que no se aprobaron', function (EstadoSolicitud $estado): void {
    // Una preparación solo nace al aprobar, así que este caso no debería
    // existir. El tablero lo descarta igual en vez de confiar en ello.
    $suelta = Solicitud::factory()->create(['fecha' => '2026-10-05', 'estado' => $estado]);
    Preparacion::factory()->create(['solicitud_id' => $suelta->id]);
    montajeParaPantalla('2026-10-05');

    Livewire::actingAs($this->administrativo)
        ->test(TableroDiario::class)
        ->set('fecha', '2026-10-05')
        ->assertViewHas('montajes', fn ($m): bool => $m->count() === 1
            && $m->first()->solicitud->estado === EstadoSolicitud::Aprobada);
})->with([
    'pendiente' => EstadoSolicitud::Pendiente,
    'revisada' => EstadoSolicitud::Revisada,
    'rechazada' => EstadoSolicitud::Rechazada,
]);

it('ordena los escenarios por hora de inicio', function (): void {
    montajeParaPantalla('2026-10-05', '14:00:00', '16:00:00');
    montajeParaPantalla('2026-10-05', '07:00:00', '09:00:00');
    montajeParaPantalla('2026-10-05', '10:00:00', '12:00:00');

    Livewire::actingAs($this->administrativo)
        ->test(TableroDiario::class)
        ->set('fecha', '2026-10-05')
        ->assertViewHas('montajes', fn ($m): bool => $m->pluck('solicitud.hora_inicio')->all()
            === ['07:00:00', '10:00:00', '14:00:00']);
});

it('enseña lo importante sin abrir el escenario', function (): void {
    $docente = User::factory()->docente()->create(['nombre' => 'Ana Gómez']);
    $caso = CasoClinico::factory()->create(['nombre' => 'Atención de parto']);
    $solicitud = Solicitud::factory()->aprobada()->create([
        'docente_id' => $docente->id,
        'caso_clinico_id' => $caso->id,
        'fecha' => '2026-10-05',
        'hora_inicio' => '07:00:00',
        'hora_fin' => '09:00:00',
        'cantidad_estudiantes' => 12,
    ]);
    Preparacion::factory()->create([
        'solicitud_id' => $solicitud->id,
        'sala_id' => Sala::factory()->create(['nombre' => 'Sala 1'])->id,
    ]);

    Livewire::actingAs($this->administrativo)
        ->test(TableroDiario::class)
        ->set('fecha', '2026-10-05')
        ->assertSee('07:00–09:00')
        ->assertSee('Atención de parto')
        ->assertSee('Ana Gómez')
        ->assertSee('12')
        ->assertSee('Sala 1')
        ->assertSee('Pendiente');
});

it('distingue la práctica de la evaluación con texto, no solo con color', function (): void {
    montajeParaPantalla('2026-10-05', tipo: TipoSesion::Evaluacion);

    Livewire::actingAs($this->administrativo)
        ->test(TableroDiario::class)
        ->set('fecha', '2026-10-05')
        ->assertSee('Evaluación');
});

it('avisa cuando la sala todavía no está asignada', function (): void {
    montajeParaPantalla('2026-10-05');

    Livewire::actingAs($this->administrativo)
        ->test(TableroDiario::class)
        ->set('fecha', '2026-10-05')
        ->assertSee('Sin asignar');
});

// ---------------------------------------------------------------------
// Sala
// ---------------------------------------------------------------------

it('asigna una sala libre', function (): void {
    $montaje = montajeParaPantalla('2026-10-05');
    $sala = Sala::factory()->create(['nombre' => 'Sala 1']);

    Livewire::actingAs($this->administrativo)
        ->test(TableroDiario::class)
        ->set('fecha', '2026-10-05')
        ->call('abrir', $montaje->id)
        ->set('salaElegida', $sala->id)
        ->call('asignarSala', $montaje->id);

    expect($montaje->fresh()->sala_id)->toBe($sala->id);
});

it('solo ofrece las salas libres de esa franja', function (): void {
    $ocupada = Sala::factory()->create(['nombre' => 'Sala ocupada']);
    $libre = Sala::factory()->create(['nombre' => 'Sala libre']);

    montajeParaPantalla('2026-10-05', '07:00:00', '09:00:00', sala: $ocupada);
    $nuevo = montajeParaPantalla('2026-10-05', '08:00:00', '10:00:00');

    Livewire::actingAs($this->administrativo)
        ->test(TableroDiario::class)
        ->set('fecha', '2026-10-05')
        ->call('abrir', $nuevo->id)
        ->assertViewHas('salasLibres', fn ($s): bool => $s->pluck('nombre')->all() === ['Sala libre']);
});

it('sí ofrece una sala usada en una franja que no se pisa', function (): void {
    $sala = Sala::factory()->create(['nombre' => 'Sala 1']);
    montajeParaPantalla('2026-10-05', '07:00:00', '09:00:00', sala: $sala);
    $nuevo = montajeParaPantalla('2026-10-05', '09:00:00', '11:00:00');

    Livewire::actingAs($this->administrativo)
        ->test(TableroDiario::class)
        ->set('fecha', '2026-10-05')
        ->call('abrir', $nuevo->id)
        ->assertViewHas('salasLibres', fn ($s): bool => $s->contains('nombre', 'Sala 1'));
});

it('muestra el choque cuando la sala se ocupó entre medias', function (): void {
    // La lista solo ofrece libres, pero entre que se pinta y se pulsa otro
    // administrativo puede haberla tomado. El Service lo detecta igual.
    $sala = Sala::factory()->create(['nombre' => 'Sala 1']);
    $docente = User::factory()->docente()->create(['nombre' => 'Ana Gómez']);
    $caso = CasoClinico::factory()->create(['nombre' => 'Herida por arma']);

    $ocupante = Solicitud::factory()->aprobada()->create([
        'docente_id' => $docente->id, 'caso_clinico_id' => $caso->id,
        'fecha' => '2026-10-05', 'hora_inicio' => '07:00:00', 'hora_fin' => '09:00:00',
    ]);
    Preparacion::factory()->create(['solicitud_id' => $ocupante->id, 'sala_id' => $sala->id]);

    $nuevo = montajeParaPantalla('2026-10-05', '08:00:00', '10:00:00');

    Livewire::actingAs($this->administrativo)
        ->test(TableroDiario::class)
        ->set('fecha', '2026-10-05')
        ->call('abrir', $nuevo->id)
        ->set('salaElegida', $sala->id)
        ->call('asignarSala', $nuevo->id)
        ->assertSee('ya está ocupada de 07:00 a 09:00')
        ->assertSee('Herida por arma')
        ->assertSee('Ana Gómez');

    expect($nuevo->fresh()->sala_id)->toBeNull();
});

// ---------------------------------------------------------------------
// Alistamiento
// ---------------------------------------------------------------------

it('marca y desmarca un equipo y actualiza el progreso', function (): void {
    $montaje = montajeParaPantalla('2026-10-05');
    $item = ItemInventario::factory()->create(['nombre' => 'Maniquí de parto']);
    $montaje->items()->attach($item->id, ['cantidad' => 1, 'alistado' => false]);

    $componente = Livewire::actingAs($this->administrativo)
        ->test(TableroDiario::class)
        ->set('fecha', '2026-10-05')
        ->call('abrir', $montaje->id)
        ->assertSee('0 de 1');

    $componente->call('alternarItem', $montaje->id, $item->id)->assertSee('1 de 1');
    expect($montaje->items()->first()->pivot->alistado)->toBeTrue();

    $componente->call('alternarItem', $montaje->id, $item->id)->assertSee('0 de 1');
    expect($montaje->items()->first()->pivot->alistado)->toBeFalse();
});

it('guarda las observaciones del alistamiento', function (): void {
    $montaje = montajeParaPantalla('2026-10-05');

    Livewire::actingAs($this->administrativo)
        ->test(TableroDiario::class)
        ->set('fecha', '2026-10-05')
        ->call('abrir', $montaje->id)
        ->set('observaciones', 'El monitor de la sala falla.')
        ->call('guardarObservaciones', $montaje->id);

    expect($montaje->fresh()->observaciones)->toBe('El monitor de la sala falla.');
});

// ---------------------------------------------------------------------
// Estado del montaje
// ---------------------------------------------------------------------

it('no ofrece marcar como preparado sin sala y explica por qué', function (): void {
    $montaje = montajeParaPantalla('2026-10-05', estado: EstadoPreparacion::EnPreparacion);

    Livewire::actingAs($this->administrativo)
        ->test(TableroDiario::class)
        ->set('fecha', '2026-10-05')
        ->call('abrir', $montaje->id)
        ->assertSee('Asigna una sala antes de dar el escenario por preparado')
        ->assertDontSee('Marcar como preparado');
});

it('no deja marcar como preparado sin sala aunque se llame al método', function (): void {
    $montaje = montajeParaPantalla('2026-10-05', estado: EstadoPreparacion::EnPreparacion);

    Livewire::actingAs($this->administrativo)
        ->test(TableroDiario::class)
        ->set('fecha', '2026-10-05')
        ->call('abrir', $montaje->id)
        ->call('cambiarEstado', $montaje->id, 'preparado')
        ->assertSee('sin sala asignada');

    expect($montaje->fresh()->estado)->toBe(EstadoPreparacion::EnPreparacion);
});

it('recorre el montaje de pendiente a preparado', function (): void {
    $montaje = montajeParaPantalla('2026-10-05', sala: Sala::factory()->create());

    $componente = Livewire::actingAs($this->administrativo)
        ->test(TableroDiario::class)
        ->set('fecha', '2026-10-05')
        ->call('abrir', $montaje->id)
        ->call('cambiarEstado', $montaje->id, 'en_preparacion');

    expect($montaje->fresh()->estado)->toBe(EstadoPreparacion::EnPreparacion);

    $componente->call('cambiarEstado', $montaje->id, 'preparado');

    $montaje->refresh();
    expect($montaje->estado)->toBe(EstadoPreparacion::Preparado)
        ->and($montaje->preparado_por)->toBe($this->administrativo->id)
        ->and($montaje->preparado_at)->not->toBeNull();
});

it('al volver de preparado limpia quién y cuándo lo montó', function (): void {
    $montaje = montajeParaPantalla('2026-10-05', estado: EstadoPreparacion::Preparado, sala: Sala::factory()->create());
    $montaje->update(['preparado_por' => $this->administrativo->id, 'preparado_at' => now()]);

    Livewire::actingAs($this->administrativo)
        ->test(TableroDiario::class)
        ->set('fecha', '2026-10-05')
        ->call('abrir', $montaje->id)
        ->call('cambiarEstado', $montaje->id, 'en_preparacion');

    $montaje->refresh();
    expect($montaje->estado)->toBe(EstadoPreparacion::EnPreparacion)
        ->and($montaje->preparado_por)->toBeNull()
        ->and($montaje->preparado_at)->toBeNull();
});

it('no muestra la fecha de montaje de un escenario que volvió atrás', function (): void {
    $montaje = montajeParaPantalla('2026-10-05', estado: EstadoPreparacion::Preparado, sala: Sala::factory()->create());
    $montaje->update(['preparado_por' => $this->administrativo->id, 'preparado_at' => now()]);

    Livewire::actingAs($this->administrativo)
        ->test(TableroDiario::class)
        ->set('fecha', '2026-10-05')
        ->assertSee('Montado el')
        ->call('abrir', $montaje->id)
        ->call('cambiarEstado', $montaje->id, 'en_preparacion')
        ->assertDontSee('Montado el');
});

// ---------------------------------------------------------------------
// Acceso
// ---------------------------------------------------------------------

it('deja entrar al tablero a administrativo y coordinador', function (string $quien): void {
    $this->actingAs($this->$quien)->get(route('panel.preparaciones'))
        ->assertOk()
        ->assertSee('Preparación de escenarios');
})->with(['administrativo', 'coordinadora']);

it('no deja entrar al tablero a docente, estudiante ni ADMIN', function (Rol $rol): void {
    // El §6.1 deja al ADMIN fuera del montaje: administra la plataforma, no
    // el laboratorio.
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);

    $this->actingAs($usuario->fresh())->get(route('panel.preparaciones'))->assertForbidden();
})->with([Rol::Docente, Rol::Estudiante, Rol::Admin]);

it('no deja tocar el montaje a quien no puede, aunque llame al método', function (Rol $rol): void {
    $montaje = montajeParaPantalla('2026-10-05');
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);

    Livewire::actingAs($usuario->fresh())
        ->test(TableroDiario::class)
        ->call('cambiarEstado', $montaje->id, 'en_preparacion')
        ->assertForbidden();

    expect($montaje->fresh()->estado)->toBe(EstadoPreparacion::Pendiente);
})->with([Rol::Docente, Rol::Estudiante, Rol::Admin]);

it('no genera consultas N+1 al recorrer el tablero', function (): void {
    foreach (range(1, 6) as $i) {
        $montaje = montajeParaPantalla('2026-10-05', sprintf('%02d:00:00', 7 + $i), sprintf('%02d:00:00', 8 + $i), sala: Sala::factory()->create());
        $montaje->items()->attach(ItemInventario::factory()->create()->id, ['cantidad' => 1, 'alistado' => false]);
    }

    Model::preventLazyLoading();

    Livewire::actingAs($this->administrativo)
        ->test(TableroDiario::class)
        ->set('fecha', '2026-10-05')
        ->assertOk();
});
