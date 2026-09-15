<?php

declare(strict_types=1);

use App\Enums\EstadoSolicitud;
use App\Enums\Rol;
use App\Enums\TipoSesion;
use App\Livewire\Solicitud\FormularioSolicitud;
use App\Livewire\Solicitud\MisSolicitudes;
use App\Models\CasoClinico;
use App\Models\ItemInventario;
use App\Models\Materia;
use App\Models\Preparacion;
use App\Models\Sala;
use App\Models\Solicitud;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->docente = User::factory()->docente()->create();
});

afterEach(function (): void {
    Model::preventLazyLoading(false);
});

/** Un caso clínico con su inventario asociado y sus cantidades. */
function casoConEquipos(array $cantidadesPorNombre): CasoClinico
{
    $caso = CasoClinico::factory()->create(['nombre' => 'Atención de parto']);

    foreach ($cantidadesPorNombre as $nombre => $cantidad) {
        $item = ItemInventario::factory()->create(['nombre' => $nombre, 'cantidad_total' => 20]);
        $caso->items()->attach($item->id, ['cantidad' => $cantidad]);
    }

    return $caso;
}

// ---------------------------------------------------------------------
// Crear solicitud
// ---------------------------------------------------------------------

it('precarga los equipos del caso clínico al elegirlo', function (): void {
    $caso = casoConEquipos(['Maniquí de parto' => 1, 'Monitor de signos' => 2]);

    Livewire::actingAs($this->docente)
        ->test(FormularioSolicitud::class)
        ->set('casoClinicoId', $caso->id)
        ->assertSet('items', $caso->items->mapWithKeys(
            fn ($i) => [$i->id => (int) $i->pivot->cantidad],
        )->all())
        ->assertSee('Maniquí de parto')
        ->assertSee('Monitor de signos');
});

it('vacía los equipos si el docente deshace la elección del caso clínico', function (): void {
    $caso = casoConEquipos(['Maniquí de parto' => 1]);

    Livewire::actingAs($this->docente)
        ->test(FormularioSolicitud::class)
        ->set('casoClinicoId', $caso->id)
        ->set('casoClinicoId', null)
        ->assertSet('items', []);
});

it('deja al docente ajustar cantidades, quitar y agregar equipos', function (): void {
    $caso = casoConEquipos(['Maniquí de parto' => 1]);
    $maniqui = $caso->items->first();
    $extra = ItemInventario::factory()->create(['nombre' => 'Camilla']);

    $componente = Livewire::actingAs($this->docente)
        ->test(FormularioSolicitud::class)
        ->set('casoClinicoId', $caso->id)
        ->set("items.{$maniqui->id}", 3)
        ->set('itemAAgregar', $extra->id)
        ->call('agregarItem');

    expect($componente->get('items'))->toBe([$maniqui->id => 3, $extra->id => 1]);

    $componente->call('quitarItem', $maniqui->id);
    expect($componente->get('items'))->toBe([$extra->id => 1]);
});

it('crea la solicitud con lo que el docente ajustó', function (): void {
    $caso = casoConEquipos(['Maniquí de parto' => 1]);
    $maniqui = $caso->items->first();
    $materia = Materia::factory()->create();

    Livewire::actingAs($this->docente)
        ->test(FormularioSolicitud::class)
        ->set('casoClinicoId', $caso->id)
        ->set('materiaId', $materia->id)
        ->set('tipo', TipoSesion::Evaluacion->value)
        ->set('fecha', '2026-10-05')
        ->set('horaInicio', '07:00')
        ->set('horaFin', '09:00')
        ->set('cantidadEstudiantes', 14)
        ->set("items.{$maniqui->id}", 4)
        ->call('guardar')
        ->assertHasNoErrors()
        ->assertRedirect(route('panel.mis-solicitudes'));

    $solicitud = Solicitud::firstOrFail();
    expect($solicitud->docente_id)->toBe($this->docente->id)
        ->and($solicitud->tipo)->toBe(TipoSesion::Evaluacion)
        ->and($solicitud->estado)->toBe(EstadoSolicitud::Pendiente)
        ->and($solicitud->cantidad_estudiantes)->toBe(14)
        ->and($solicitud->items->firstWhere('id', $maniqui->id)->pivot->cantidad)->toBe(4);
});

it('exige los campos obligatorios', function (): void {
    Livewire::actingAs($this->docente)
        ->test(FormularioSolicitud::class)
        ->call('guardar')
        ->assertHasErrors(['materiaId', 'casoClinicoId', 'fecha', 'horaInicio', 'horaFin', 'cantidadEstudiantes']);
});

it('no acepta una franja que termina antes de empezar', function (): void {
    Livewire::actingAs($this->docente)
        ->test(FormularioSolicitud::class)
        ->set('horaInicio', '10:00')
        ->set('horaFin', '08:00')
        ->call('guardar')
        ->assertHasErrors(['horaFin']);
});

it('no ofrece campo de sala al solicitar y lo explica', function (): void {
    // Regla 4 de CLAUDE.md: la sala la asigna el administrativo durante la
    // preparación. Si el formulario la pidiera, el dato no tendría dónde ir.
    $this->actingAs($this->docente)->get(route('panel.solicitudes.nueva'))
        ->assertOk()
        ->assertSee('La sala no se elige aquí')
        ->assertDontSee('name="sala', escape: false)
        ->assertDontSee('wire:model="salaId"', escape: false);
});

it('no enseña al docente ninguna existencia de inventario', function (): void {
    // RF40: los docentes no ven disponibilidad, ni directa ni indirectamente.
    $caso = casoConEquipos(['Maniquí de parto' => 1]);

    Livewire::actingAs($this->docente)
        ->test(FormularioSolicitud::class)
        ->set('casoClinicoId', $caso->id)
        ->assertDontSee('Disponible')
        ->assertDontSee('disponibles')
        ->assertDontSee('cantidad_total')
        ->assertDontSee('existencias');
});

// ---------------------------------------------------------------------
// Historial
// ---------------------------------------------------------------------

it('muestra el historial del docente con su estado', function (): void {
    Solicitud::factory()->count(2)->create(['docente_id' => $this->docente->id]);
    Solicitud::factory()->create(); // de otro docente

    Livewire::actingAs($this->docente)
        ->test(MisSolicitudes::class)
        ->assertViewHas('solicitudes', fn ($p): bool => $p->total() === 2);
});

it('filtra el historial por estado', function (): void {
    Solicitud::factory()->create(['docente_id' => $this->docente->id, 'estado' => EstadoSolicitud::Pendiente]);
    Solicitud::factory()->aprobada()->create(['docente_id' => $this->docente->id]);

    Livewire::actingAs($this->docente)
        ->test(MisSolicitudes::class)
        ->assertViewHas('solicitudes', fn ($p): bool => $p->total() === 2)
        ->set('estado', EstadoSolicitud::Aprobada->value)
        ->assertViewHas('solicitudes', fn ($p): bool => $p->total() === 1);
});

it('enseña al docente el motivo por el que le rechazaron la solicitud', function (): void {
    Solicitud::factory()->create([
        'docente_id' => $this->docente->id,
        'estado' => EstadoSolicitud::Rechazada,
        'motivo_rechazo' => 'La sala está en mantenimiento ese día.',
    ]);

    Livewire::actingAs($this->docente)
        ->test(MisSolicitudes::class)
        ->assertSee('Motivo del rechazo')
        ->assertSee('La sala está en mantenimiento ese día.');
});

it('no enseña la sala hasta que el administrativo la asigna', function (): void {
    $solicitud = Solicitud::factory()->aprobada()->create(['docente_id' => $this->docente->id]);
    $preparacion = Preparacion::factory()->create(['solicitud_id' => $solicitud->id, 'sala_id' => null]);

    Livewire::actingAs($this->docente)
        ->test(MisSolicitudes::class)
        ->assertSee('Aún sin asignar')
        ->assertDontSee('Sala de partos');

    $preparacion->update(['sala_id' => Sala::factory()->create(['nombre' => 'Sala de partos'])->id]);

    Livewire::actingAs($this->docente)
        ->test(MisSolicitudes::class)
        ->assertSee('Sala de partos')
        ->assertDontSee('Aún sin asignar');
});

it('no genera consultas N+1 al recorrer el historial', function (): void {
    Solicitud::factory()->count(6)->aprobada()->create(['docente_id' => $this->docente->id])
        ->each(fn (Solicitud $s) => Preparacion::factory()->conSala()->create(['solicitud_id' => $s->id]));

    Model::preventLazyLoading();

    Livewire::actingAs($this->docente)->test(MisSolicitudes::class)->assertOk();
});

// ---------------------------------------------------------------------
// Acceso
// ---------------------------------------------------------------------

it('no deja al docente ver la bandeja de revisión', function (): void {
    $this->actingAs($this->docente)->get(route('panel.solicitudes'))->assertForbidden();
});

it('no deja a quien no es docente entrar al formulario', function (Rol $rol): void {
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);

    $this->actingAs($usuario->fresh())->get(route('panel.solicitudes.nueva'))->assertForbidden();
})->with([Rol::Administrativo, Rol::Coordinador, Rol::Estudiante, Rol::Admin]);
