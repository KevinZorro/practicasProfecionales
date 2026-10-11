<?php

declare(strict_types=1);

use App\Enums\EstadoSolicitud;
use App\Enums\Rol;
use App\Livewire\Solicitud\ExpedienteDeSolicitud;
use App\Models\ItemInventario;
use App\Models\Preparacion;
use App\Models\Solicitud;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->administrativo = User::factory()->administrativo()->create();
    $this->coordinadora = User::factory()->coordinador()->create();
    $this->admin = User::factory()->admin()->create();
});

afterEach(function (): void {
    Model::preventLazyLoading(false);
});

function expedienteDe(User $usuario, Solicitud $solicitud): Testable
{
    return Livewire::actingAs($usuario)->test(ExpedienteDeSolicitud::class, ['solicitud' => $solicitud]);
}

// ---------------------------------------------------------------------
// La página
// ---------------------------------------------------------------------

it('abre el expediente con su cabecera, su línea de estados y sus pestañas', function (): void {
    $solicitud = Solicitud::factory()->revisada()->create();

    $this->actingAs($this->coordinadora)
        ->get(route('panel.solicitudes.ver', $solicitud))
        ->assertOk()
        ->assertSee($solicitud->casoClinico->nombre)
        ->assertSee('Fases de la solicitud')
        ->assertSee('Por aprobar')
        ->assertSee('Detalle')
        ->assertSee('Participantes');
});

it('deja al docente abrir el expediente de su solicitud, pero no el de otro', function (): void {
    $docente = User::factory()->docente()->create();
    $suya = Solicitud::factory()->create(['docente_id' => $docente->id]);
    $ajena = Solicitud::factory()->create();

    $this->actingAs($docente)->get(route('panel.solicitudes.ver', $suya))->assertOk();
    $this->actingAs($docente)->get(route('panel.solicitudes.ver', $ajena))->assertForbidden();
});

it('no deja a un estudiante abrir un expediente', function (): void {
    $solicitud = Solicitud::factory()->create();

    $this->actingAs(User::factory()->estudiante()->create())
        ->get(route('panel.solicitudes.ver', $solicitud))
        ->assertForbidden();
});

it('enseña la pestaña de novedades solo con la sesión aprobada y a quien puede reprogramarla', function (): void {
    $aprobada = Solicitud::factory()->aprobada()->create();
    $pendiente = Solicitud::factory()->create();

    $this->actingAs($this->administrativo)->get(route('panel.solicitudes.ver', $aprobada))->assertSee('Novedades');
    $this->actingAs($this->administrativo)->get(route('panel.solicitudes.ver', $pendiente))->assertDontSee('Novedades');
    $this->actingAs($this->admin)->get(route('panel.solicitudes.ver', $aprobada))->assertDontSee('Novedades');
});

// ---------------------------------------------------------------------
// Qué decisión ve cada rol
// ---------------------------------------------------------------------

it('enseña al administrativo revisar y rechazar una pendiente, pero no aprobar', function (): void {
    expedienteDe($this->administrativo, Solicitud::factory()->create(['estado' => EstadoSolicitud::Pendiente]))
        ->assertSee('Marcar como revisada')
        ->assertSee('Rechazar')
        ->assertDontSee('Aprobar');
});

it('no le enseña al administrativo ningún control sobre lo que ya aceptó', function (): void {
    expedienteDe($this->administrativo, Solicitud::factory()->revisada()->create())
        ->assertDontSee('Aprobar')
        ->assertDontSee('Rechazar')
        ->assertSee('Espera la aprobación de coordinación.');
});

it('enseña a la coordinadora revisar una pendiente y aprobar una revisada', function (): void {
    expedienteDe($this->coordinadora, Solicitud::factory()->create(['estado' => EstadoSolicitud::Pendiente]))
        ->assertSee('Marcar como revisada')
        ->assertSee('Rechazar')
        ->assertDontSee('Aprobar');

    expedienteDe($this->coordinadora, Solicitud::factory()->revisada()->create())
        ->assertSee('Aprobar')
        ->assertSee('Rechazar')
        ->assertDontSee('Marcar como revisada');
});

it('enseña al ADMIN aprobar y rechazar lo revisado, pero no revisar', function (): void {
    expedienteDe($this->admin, Solicitud::factory()->revisada()->create())
        ->assertSee('Aprobar')
        ->assertSee('Rechazar')
        ->assertDontSee('Marcar como revisada');
});

it('no le enseña al ADMIN el rechazo de una pendiente, que es de la primera fase', function (): void {
    expedienteDe($this->admin, Solicitud::factory()->create(['estado' => EstadoSolicitud::Pendiente]))
        ->assertDontSee('Rechazar')
        ->assertSee('Espera la revisión del laboratorio.');
});

it('no le enseña al docente ninguna decisión sobre su propia solicitud', function (): void {
    $docente = User::factory()->docente()->create();

    expedienteDe($docente, Solicitud::factory()->revisada()->create(['docente_id' => $docente->id]))
        ->assertDontSee('Aprobar')
        ->assertDontSee('Rechazar')
        ->assertDontSee('Marcar como revisada');
});

// ---------------------------------------------------------------------
// El flujo
// ---------------------------------------------------------------------

it('deja al administrativo marcar una solicitud como revisada y recarga el expediente', function (): void {
    $solicitud = Solicitud::factory()->create(['estado' => EstadoSolicitud::Pendiente]);

    expedienteDe($this->administrativo, $solicitud)
        ->call('revisar')
        ->assertDispatched('aviso-de-la-accion')
        ->assertRedirect(route('panel.solicitudes.ver', $solicitud));

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Revisada)
        ->and($solicitud->fresh()->revisada_por)->toBe($this->administrativo->id);
});

it('deja a la coordinadora aprobar y crea la preparación', function (): void {
    $solicitud = Solicitud::factory()->revisada()->create();

    expedienteDe($this->coordinadora, $solicitud)->call('aprobar')->assertRedirect(route('panel.solicitudes.ver', $solicitud));

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Aprobada)
        ->and($solicitud->fresh()->preparacion)->not->toBeNull();
});

it('deja al ADMIN aprobar una solicitud revisada cuando la coordinadora no está', function (): void {
    $solicitud = Solicitud::factory()->revisada()->create();

    expedienteDe($this->admin, $solicitud)->call('aprobar');

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Aprobada)
        ->and($solicitud->fresh()->preparacion)->not->toBeNull();
});

it('no deja al administrativo aprobar aunque llame al método a mano', function (): void {
    $solicitud = Solicitud::factory()->revisada()->create();

    expedienteDe($this->administrativo, $solicitud)->call('aprobar')->assertForbidden();

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Revisada);
});

it('no deja aprobar una solicitud sin revisar aunque llamen al método a mano', function (string $quien): void {
    $solicitud = Solicitud::factory()->create(['estado' => EstadoSolicitud::Pendiente]);

    expedienteDe($this->$quien, $solicitud)->call('aprobar')->assertForbidden();

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Pendiente);
})->with(['coordinadora', 'admin']);

it('no deja al docente decidir sobre su solicitud aunque llame al método a mano', function (string $metodo): void {
    $docente = User::factory()->docente()->create();
    $solicitud = Solicitud::factory()->revisada()->create(['docente_id' => $docente->id]);

    expedienteDe($docente, $solicitud)->call($metodo)->assertForbidden();

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Revisada);
})->with(['revisar', 'aprobar', 'rechazar']);

it('deja al administrativo rechazar una pendiente', function (): void {
    $solicitud = Solicitud::factory()->create();

    expedienteDe($this->administrativo, $solicitud)
        ->call('pedirMotivo')
        ->set('motivoRechazo', 'El docente pidió un simulador que está dado de baja.')
        ->call('rechazar')
        ->assertHasNoErrors();

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Rechazada)
        ->and($solicitud->fresh()->resuelta_por)->toBe($this->administrativo->id);
});

it('no deja al administrativo rechazar lo que ya aceptó aunque llame al método a mano', function (): void {
    $solicitud = Solicitud::factory()->revisada()->create();

    expedienteDe($this->administrativo, $solicitud)->call('rechazar')->assertForbidden();

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Revisada);
});

it('pide el motivo antes de rechazar y lo guarda', function (): void {
    $solicitud = Solicitud::factory()->revisada()->create();

    expedienteDe($this->coordinadora, $solicitud)
        ->call('pedirMotivo')
        ->assertSee('Motivo del rechazo')
        ->assertSee('Confirmar rechazo')
        ->set('motivoRechazo', 'Coincide con otra práctica en la misma sala.')
        ->call('rechazar');

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Rechazada)
        ->and($solicitud->fresh()->motivo_rechazo)->toBe('Coincide con otra práctica en la misma sala.');
});

it('deja rechazar sin motivo, porque es opcional', function (): void {
    $solicitud = Solicitud::factory()->revisada()->create();

    expedienteDe($this->coordinadora, $solicitud)->call('rechazar')->assertHasNoErrors();

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Rechazada)
        ->and($solicitud->fresh()->motivo_rechazo)->toBeNull();
});

it('vuelve a la decisión al cancelar el rechazo', function (): void {
    expedienteDe($this->coordinadora, Solicitud::factory()->revisada()->create())
        ->call('pedirMotivo')
        ->set('motivoRechazo', 'Algo')
        ->call('cancelarRechazo')
        ->assertSet('rechazando', false)
        ->assertSet('motivoRechazo', '')
        ->assertSee('Aprobar');
});

it('enseña al docente el motivo por el que le rechazaron la solicitud', function (): void {
    $docente = User::factory()->docente()->create();
    $solicitud = Solicitud::factory()->create([
        'docente_id' => $docente->id,
        'estado' => EstadoSolicitud::Rechazada,
        'motivo_rechazo' => 'La sala está en mantenimiento ese día.',
    ]);

    expedienteDe($docente, $solicitud)
        ->assertSee('Motivo del rechazo')
        ->assertSee('La sala está en mantenimiento ese día.');
});

// ---------------------------------------------------------------------
// El detalle
// ---------------------------------------------------------------------

it('muestra los equipos pedidos con sus cantidades', function (): void {
    $solicitud = Solicitud::factory()->create();
    $item = ItemInventario::factory()->create(['nombre' => 'Maniquí de parto', 'cantidad_total' => 5]);
    $solicitud->items()->attach($item->id, ['cantidad' => 2]);

    expedienteDe($this->administrativo, $solicitud)
        ->assertSee('Maniquí de parto')
        ->assertSee('×2', escape: false);
});

it('avisa cuando se piden más unidades de las que quedan libres', function (): void {
    $item = ItemInventario::factory()->create(['cantidad_total' => 3]);

    $yaAprobada = Solicitud::factory()->aprobada()->create([
        'fecha' => '2026-10-05', 'hora_inicio' => '07:00:00', 'hora_fin' => '09:00:00',
    ]);
    $yaAprobada->items()->attach($item->id, ['cantidad' => 2]);

    $nueva = Solicitud::factory()->create([
        'fecha' => '2026-10-05', 'hora_inicio' => '08:00:00', 'hora_fin' => '10:00:00',
    ]);
    $nueva->items()->attach($item->id, ['cantidad' => 3]);

    expedienteDe($this->administrativo, $nueva)
        ->assertSee('más unidades de las que quedan libres')
        ->assertSee('Solo quedan 1 libres en esa franja');
});

it('no le enseña al docente la disponibilidad del inventario (regla 5)', function (): void {
    $docente = User::factory()->docente()->create();
    $item = ItemInventario::factory()->create(['cantidad_total' => 1]);
    $solicitud = Solicitud::factory()->create(['docente_id' => $docente->id]);
    $solicitud->items()->attach($item->id, ['cantidad' => 9]);

    expedienteDe($docente, $solicitud)
        ->assertDontSee('más unidades de las que quedan libres')
        ->assertDontSee('libres en esa franja');
});

it('no avisa de faltantes en una aprobada, que ya cuenta entre lo comprometido', function (): void {
    $item = ItemInventario::factory()->create(['cantidad_total' => 3]);
    $solicitud = Solicitud::factory()->aprobada()->create();
    $solicitud->items()->attach($item->id, ['cantidad' => 3]);

    expedienteDe($this->administrativo, $solicitud)->assertDontSee('libres en esa franja');
});

it('no impide aprobar aunque falten unidades', function (): void {
    // Es información para quien decide, no un bloqueo. Si el cliente decide
    // después que debe bloquear, la regla entra en el Service.
    $item = ItemInventario::factory()->create(['cantidad_total' => 1]);
    $solicitud = Solicitud::factory()->revisada()->create();
    $solicitud->items()->attach($item->id, ['cantidad' => 9]);

    expedienteDe($this->coordinadora, $solicitud)->call('aprobar')->assertHasNoErrors();

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Aprobada);
});

it('dice quién revisó y quién aprobó', function (): void {
    $revisora = User::factory()->administrativo()->create(['nombre' => 'Rosa Revisora']);
    $aprobadora = User::factory()->coordinador()->create(['nombre' => 'Ana Aprobadora']);
    $solicitud = Solicitud::factory()->aprobada()->create([
        'revisada_por' => $revisora->id, 'revisada_at' => now(),
        'resuelta_por' => $aprobadora->id, 'resuelta_at' => now(),
    ]);

    expedienteDe($this->administrativo, $solicitud)
        ->assertSee('Rosa Revisora')
        ->assertSee('Aprobó')
        ->assertSee('Ana Aprobadora');
});

it('lleva desde el montaje a las preparaciones de ese día', function (): void {
    $solicitud = Solicitud::factory()->aprobada()->create(['fecha' => '2026-11-03']);
    Preparacion::factory()->create(['solicitud_id' => $solicitud->id]);

    expedienteDe($this->administrativo, $solicitud)
        ->assertSee('Montaje')
        ->assertSee(route('panel.preparaciones', ['fecha' => '2026-11-03']), escape: false);
});

it('no genera consultas N+1 al pintar el expediente', function (): void {
    $solicitud = Solicitud::factory()->aprobada()->create();
    Preparacion::factory()->conSala()->create(['solicitud_id' => $solicitud->id]);
    $solicitud->items()->attach(ItemInventario::factory()->count(3)->create()->modelKeys(), ['cantidad' => 1]);

    Model::preventLazyLoading();

    $this->actingAs($this->administrativo)->get(route('panel.solicitudes.ver', $solicitud))->assertOk();
});

// ---------------------------------------------------------------------
// Con el rol activo, en una petición de verdad
// ---------------------------------------------------------------------

it('no deja a un docente ni a un estudiante abrir el detalle de una solicitud ajena', function (Rol $rol): void {
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);
    $solicitud = Solicitud::factory()->create();

    // El detalle pide su permiso en cada petición, también en la primera:
    // ni siquiera se abre, así que no hay revisar() al que llamar.
    Livewire::actingAs($usuario->fresh())
        ->test(ExpedienteDeSolicitud::class, ['solicitud' => $solicitud])
        ->assertForbidden();

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Pendiente);
})->with([Rol::Docente, Rol::Estudiante]);
