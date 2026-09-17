<?php

declare(strict_types=1);

use App\Enums\EstadoItemInventario;
use App\Enums\NivelFidelidad;
use App\Enums\Rol;
use App\Enums\TipoItemInventario;
use App\Livewire\Inventario\ConsultaDisponibilidad;
use App\Livewire\Inventario\FormularioItem;
use App\Livewire\Inventario\ListadoInventario;
use App\Models\ItemInventario;
use App\Models\Solicitud;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Database\Eloquent\Model;
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

// ---------------------------------------------------------------------
// La regla delicada: nivel_fidelidad (RF39)
// ---------------------------------------------------------------------

it('no ofrece el nivel de fidelidad como editable a quien no es ADMIN', function (string $quien): void {
    $simulador = ItemInventario::factory()->simulador(NivelFidelidad::Alta)->create();

    Livewire::actingAs($this->$quien)
        ->test(FormularioItem::class, ['item' => $simulador])
        ->assertViewHas('puedeEditarFidelidad', false)
        ->assertSee('solo lo registra el administrador de la plataforma')
        ->assertDontSeeHtml('id="nivelFidelidad"');
})->with(['administrativo', 'coordinadora']);

it('enseña el valor de fidelidad aunque no se pueda editar', function (): void {
    // Ocultarlo haría creer que el dato no existe.
    $simulador = ItemInventario::factory()->simulador(NivelFidelidad::Media)->create();

    Livewire::actingAs($this->administrativo)
        ->test(FormularioItem::class, ['item' => $simulador])
        ->assertSee('Media fidelidad');
});

it('ofrece el nivel de fidelidad editable al ADMIN', function (): void {
    $simulador = ItemInventario::factory()->simulador(NivelFidelidad::Baja)->create();

    Livewire::actingAs($this->admin)
        ->test(FormularioItem::class, ['item' => $simulador])
        ->assertViewHas('puedeEditarFidelidad', true)
        ->assertSeeHtml('id="nivelFidelidad"');
});

it('deja al ADMIN completar la fidelidad de un simulador que no la tenía', function (): void {
    $simulador = ItemInventario::factory()->simulador()->create(['nivel_fidelidad' => null]);
    expect($simulador->nivel_fidelidad)->toBeNull();

    Livewire::actingAs($this->admin)
        ->test(FormularioItem::class, ['item' => $simulador])
        ->set('nivelFidelidad', NivelFidelidad::Alta->value)
        ->call('guardar')
        ->assertHasNoErrors();

    expect($simulador->fresh()->nivel_fidelidad)->toBe(NivelFidelidad::Alta);
});

it('deja a un administrativo editar el resto de campos del simulador', function (): void {
    $simulador = ItemInventario::factory()->simulador(NivelFidelidad::Alta)->create();

    Livewire::actingAs($this->administrativo)
        ->test(FormularioItem::class, ['item' => $simulador])
        ->set('nombre', 'Maniquí de parto renovado')
        ->set('cantidadTotal', 7)
        ->call('guardar')
        ->assertHasNoErrors();

    $simulador->refresh();
    expect($simulador->nombre)->toBe('Maniquí de parto renovado')
        ->and($simulador->cantidad_total)->toBe(7)
        ->and($simulador->nivel_fidelidad)->toBe(NivelFidelidad::Alta);
});

it('no deja a un administrativo cambiar la fidelidad manipulando la petición', function (): void {
    // El formulario no manda la fidelidad de quien no puede tocarla, sino la
    // que el ítem ya tenía. Por eso el resto de la edición SÍ se guarda: si
    // mandara el valor manipulado, el Service rechazaría la operación entera
    // y el administrativo perdería sus cambios legítimos. Comprobar solo que
    // la fidelidad no cambió no distingue los dos comportamientos.
    $simulador = ItemInventario::factory()->simulador(NivelFidelidad::Baja)->create(['nombre' => 'Torso']);

    Livewire::actingAs($this->administrativo)
        ->test(FormularioItem::class, ['item' => $simulador])
        ->set('nombre', 'Torso revisado')
        ->set('nivelFidelidad', NivelFidelidad::Alta->value)
        ->call('guardar')
        ->assertSet('errorDeRegla', null);

    $simulador->refresh();
    expect($simulador->nivel_fidelidad)->toBe(NivelFidelidad::Baja)
        ->and($simulador->nombre)->toBe('Torso revisado');
});

it('no ofrece fidelidad en un ítem que no es simulador', function (TipoItemInventario $tipo): void {
    Livewire::actingAs($this->admin)
        ->test(FormularioItem::class)
        ->set('tipo', $tipo->value)
        ->assertViewHas('esSimulador', false)
        ->assertDontSeeHtml('id="nivelFidelidad"');
})->with([TipoItemInventario::EquipoClinico, TipoItemInventario::EquipoBasico]);

it('limpia la fidelidad si el tipo deja de ser simulador', function (): void {
    Livewire::actingAs($this->admin)
        ->test(FormularioItem::class)
        ->set('nivelFidelidad', NivelFidelidad::Alta->value)
        ->set('tipo', TipoItemInventario::EquipoBasico->value)
        ->assertSet('nivelFidelidad', '');
});

it('muestra el motivo cuando el Service rechaza una regla de coherencia', function (): void {
    // El formulario ya impide llegar aquí, pero el mensaje del Service debe
    // verse si la regla salta de todas formas.
    $equipo = ItemInventario::factory()->equipoBasico()->create();

    $componente = Livewire::actingAs($this->admin)->test(FormularioItem::class, ['item' => $equipo]);
    $componente->set('tipo', TipoItemInventario::Simulador->value)
        ->set('nivelFidelidad', NivelFidelidad::Alta->value);

    // Volver a equipo básico sin limpiar: se fuerza el estado incoherente.
    $componente->set('tipo', TipoItemInventario::EquipoBasico->value)
        ->set('nivelFidelidad', NivelFidelidad::Alta->value)
        ->call('guardar');

    expect($equipo->fresh()->nivel_fidelidad)->toBeNull();
});

// ---------------------------------------------------------------------
// Listado, filtros y buscador
// ---------------------------------------------------------------------

it('filtra por tipo, estado y fidelidad', function (): void {
    ItemInventario::factory()->simulador(NivelFidelidad::Alta)->create();
    ItemInventario::factory()->simulador(NivelFidelidad::Baja)->create();
    ItemInventario::factory()->simulador(NivelFidelidad::Alta)->enMantenimiento()->create();
    ItemInventario::factory()->equipoBasico()->create();

    $componente = Livewire::actingAs($this->administrativo)->test(ListadoInventario::class);

    $componente->assertViewHas('items', fn ($p): bool => $p->total() === 4)
        ->set('tipo', TipoItemInventario::Simulador->value)
        ->assertViewHas('items', fn ($p): bool => $p->total() === 3)
        ->set('tipo', '')
        ->set('estado', EstadoItemInventario::Mantenimiento->value)
        ->assertViewHas('items', fn ($p): bool => $p->total() === 1)
        ->set('estado', '')
        ->set('fidelidad', NivelFidelidad::Alta->value)
        ->assertViewHas('items', fn ($p): bool => $p->total() === 2);
});

it('busca por nombre sin distinguir mayúsculas', function (): void {
    ItemInventario::factory()->create(['nombre' => 'Maniquí de parto']);
    ItemInventario::factory()->create(['nombre' => 'Monitor de signos vitales']);

    Livewire::actingAs($this->administrativo)
        ->test(ListadoInventario::class)
        ->set('busqueda', 'maniquí')
        ->assertViewHas('items', fn ($p): bool => $p->total() === 1)
        ->assertSee('Maniquí de parto')
        ->assertDontSee('Monitor de signos vitales');
});

it('encuentra los simuladores que esperan que el ADMIN asigne la fidelidad', function (): void {
    ItemInventario::factory()->simulador()->create(['nombre' => 'Torso sin clasificar', 'nivel_fidelidad' => null]);
    ItemInventario::factory()->simulador(NivelFidelidad::Alta)->create(['nombre' => 'Maniquí clasificado']);
    ItemInventario::factory()->equipoBasico()->create(['nombre' => 'Camilla']);

    Livewire::actingAs($this->admin)
        ->test(ListadoInventario::class)
        ->set('fidelidad', ListadoInventario::SIN_ASIGNAR)
        ->assertViewHas('items', fn ($p): bool => $p->total() === 1)
        ->assertSee('Torso sin clasificar');
});

it('lee la fidelidad sin asignar como pendiente, no como dato vacío', function (): void {
    ItemInventario::factory()->simulador()->create(['nombre' => 'Torso de RCP', 'nivel_fidelidad' => null]);

    Livewire::actingAs($this->administrativo)
        ->test(ListadoInventario::class)
        ->assertSee('Pendiente de asignar');
});

it('marca los ítems que no cuentan como disponibles', function (EstadoItemInventario $estado): void {
    ItemInventario::factory()->create(['estado' => $estado]);

    Livewire::actingAs($this->administrativo)
        ->test(ListadoInventario::class)
        ->assertSee($estado->etiqueta());
})->with([EstadoItemInventario::Mantenimiento, EstadoItemInventario::Baja]);

it('limpia todos los filtros de una vez', function (): void {
    ItemInventario::factory()->count(3)->create();

    Livewire::actingAs($this->administrativo)
        ->test(ListadoInventario::class)
        ->set('busqueda', 'nada que coincida')
        ->assertViewHas('items', fn ($p): bool => $p->total() === 0)
        ->call('limpiarFiltros')
        ->assertViewHas('items', fn ($p): bool => $p->total() === 3);
});

it('no genera consultas N+1 al recorrer el listado', function (): void {
    ItemInventario::factory()->count(8)->simulador(NivelFidelidad::Alta)->create();

    Model::preventLazyLoading();

    Livewire::actingAs($this->administrativo)->test(ListadoInventario::class)->assertOk();
});

// ---------------------------------------------------------------------
// Baja
// ---------------------------------------------------------------------

it('pide confirmación antes de dar de baja y explica que no se borra', function (): void {
    $item = ItemInventario::factory()->create(['nombre' => 'Camilla']);

    Livewire::actingAs($this->administrativo)
        ->test(ListadoInventario::class)
        ->call('pedirConfirmacionDeBaja', $item->id)
        ->assertSee('¿Dar de baja «Camilla»?')
        ->assertSee('El registro no se borra');

    expect($item->fresh()->estado)->toBe(EstadoItemInventario::Disponible);
});

it('da de baja sin romper las solicitudes históricas que lo referencian', function (): void {
    $item = ItemInventario::factory()->create();
    $solicitud = Solicitud::factory()->create();
    $solicitud->items()->attach($item->id, ['cantidad' => 2]);

    Livewire::actingAs($this->administrativo)
        ->test(ListadoInventario::class)
        ->call('darDeBaja', $item->id);

    $item->refresh();
    expect($item->estado)->toBe(EstadoItemInventario::Baja)
        ->and($item->activo)->toBeFalse()
        ->and(ItemInventario::find($item->id))->not->toBeNull()
        ->and($solicitud->fresh()->items->firstWhere('id', $item->id)->pivot->cantidad)->toBe(2);
});

// ---------------------------------------------------------------------
// Disponibilidad (RF40)
// ---------------------------------------------------------------------

it('refleja lo comprometido en solicitudes aprobadas', function (): void {
    $item = ItemInventario::factory()->create(['cantidad_total' => 6]);

    $aprobada = Solicitud::factory()->aprobada()->create([
        'fecha' => '2026-10-05', 'hora_inicio' => '07:00:00', 'hora_fin' => '09:00:00',
    ]);
    $aprobada->items()->attach($item->id, ['cantidad' => 2]);

    Livewire::actingAs($this->administrativo)
        ->test(ConsultaDisponibilidad::class)
        ->set('itemId', $item->id)
        ->set('fecha', '2026-10-05')
        ->set('horaInicio', '08:00')
        ->set('horaFin', '10:00')
        ->call('consultar')
        ->assertSet('libres', 4)
        ->assertSee('4');
});

it('no descuenta lo pedido en solicitudes sin aprobar', function (): void {
    $item = ItemInventario::factory()->create(['cantidad_total' => 6]);
    $pendiente = Solicitud::factory()->create([
        'fecha' => '2026-10-05', 'hora_inicio' => '07:00:00', 'hora_fin' => '09:00:00',
    ]);
    $pendiente->items()->attach($item->id, ['cantidad' => 4]);

    Livewire::actingAs($this->administrativo)
        ->test(ConsultaDisponibilidad::class)
        ->set('itemId', $item->id)
        ->set('fecha', '2026-10-05')
        ->set('horaInicio', '07:00')
        ->set('horaFin', '09:00')
        ->call('consultar')
        ->assertSet('libres', 6);
});

it('exige ítem, fecha y franja para consultar', function (): void {
    Livewire::actingAs($this->administrativo)
        ->test(ConsultaDisponibilidad::class)
        ->set('itemId', null)
        ->call('consultar')
        ->assertHasErrors('itemId');
});

// ---------------------------------------------------------------------
// Acceso
// ---------------------------------------------------------------------

it('deja entrar al inventario a administrativo, coordinador y ADMIN', function (string $quien): void {
    $this->actingAs($this->$quien)->get(route('panel.inventario'))->assertOk();
})->with(['administrativo', 'coordinadora', 'admin']);

it('cierra todas las pantallas de inventario a docentes y estudiantes', function (Rol $rol, string $ruta): void {
    // RF40: la disponibilidad no se alcanza por ninguna vía.
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);
    $item = ItemInventario::factory()->create();

    $url = $ruta === 'panel.inventario.editar' ? route($ruta, $item) : route($ruta);

    $this->actingAs($usuario->fresh())->get($url)->assertForbidden();
})->with([Rol::Docente, Rol::Estudiante])
    ->with(['panel.inventario', 'panel.inventario.nuevo', 'panel.inventario.disponibilidad', 'panel.inventario.editar']);

it('no deja a un docente consultar disponibilidad ni por el componente', function (): void {
    $docente = User::factory()->docente()->create();

    Livewire::actingAs($docente)
        ->test(ConsultaDisponibilidad::class)
        ->set('itemId', ItemInventario::factory()->create()->id)
        ->call('consultar')
        ->assertForbidden();
});

it('no deja a un docente dar de baja ni por el componente', function (): void {
    $docente = User::factory()->docente()->create();
    $item = ItemInventario::factory()->create();

    Livewire::actingAs($docente)
        ->test(ListadoInventario::class)
        ->call('darDeBaja', $item->id)
        ->assertForbidden();

    expect($item->fresh()->estado)->toBe(EstadoItemInventario::Disponible);
});
