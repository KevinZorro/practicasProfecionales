<?php

declare(strict_types=1);

use App\Livewire\Inventario\FormularioItem;
use App\Models\ItemInventario;
use App\Models\Solicitud;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->administrativo = User::factory()->administrativo()->create();
});

/** El href del enlace «volver» de la página, tal como lo pinta. */
function enlaceDeVuelta(string $html): string
{
    preg_match('/<a href="([^"]*)" class="enlace-de-vuelta">/', $html, $coincidencia);

    return html_entity_decode($coincidencia[1] ?? '');
}

// ---------------------------------------------------------------------
// El expediente de una solicitud
// ---------------------------------------------------------------------

it('vuelve desde el expediente a la pantalla de la que se abrió', function (): void {
    $sesion = Solicitud::factory()->aprobada()->create();
    $apartadas = route('panel.sesiones-apartadas', ['fecha' => '2026-10-20']);

    $pagina = $this->actingAs($this->administrativo)
        ->withHeader('referer', $apartadas)
        ->get(route('panel.solicitudes.ver', $sesion));

    $pagina->assertOk()->assertSee('Sesiones apartadas');
    expect(enlaceDeVuelta($pagina->getContent()))->toBe($apartadas);
});

it('recuerda el origen del expediente al pasar de una pestaña a otra', function (): void {
    $sesion = Solicitud::factory()->aprobada()->create();
    $apartadas = route('panel.sesiones-apartadas');

    $this->actingAs($this->administrativo)
        ->withHeader('referer', $apartadas)
        ->get(route('panel.solicitudes.ver', $sesion));

    // Desde el detalle a participantes, y de ahí a los formatos de la
    // sesión y de vuelta: el origen sigue siendo las sesiones apartadas.
    $participantes = $this->withHeader('referer', route('panel.formatos-confidencialidad.estado', ['sesion' => $sesion->id]))
        ->get(route('panel.solicitudes.participantes', $sesion));

    expect(enlaceDeVuelta($participantes->getContent()))->toBe($apartadas);
});

it('no arrastra el origen de un expediente a otro', function (): void {
    $primera = Solicitud::factory()->aprobada()->create();
    $segunda = Solicitud::factory()->aprobada()->create();

    $this->actingAs($this->administrativo)
        ->withHeader('referer', route('panel.sesiones-apartadas'))
        ->get(route('panel.solicitudes.ver', $primera));

    $pagina = $this->withHeader('referer', '')->get(route('panel.solicitudes.ver', $segunda));

    expect(enlaceDeVuelta($pagina->getContent()))->toBe(route('panel.solicitudes'));
});

it('sin origen conocido, el expediente vuelve a la lista de solicitudes de quien mira', function (): void {
    $docente = User::factory()->docente()->create();
    $suya = Solicitud::factory()->create(['docente_id' => $docente->id]);

    $pagina = $this->actingAs($docente)->get(route('panel.solicitudes.ver', $suya));

    expect(enlaceDeVuelta($pagina->getContent()))->toBe(route('panel.mis-solicitudes'));
});

it('no ofrece volver a otro sitio ni a una pantalla que el rol activo no ve', function (string $origen): void {
    $sesion = Solicitud::factory()->aprobada()->create();

    $pagina = $this->actingAs($this->administrativo)
        ->withHeader('referer', $origen)
        ->get(route('panel.solicitudes.ver', $sesion));

    expect(enlaceDeVuelta($pagina->getContent()))->toBe(route('panel.solicitudes'));
})->with([
    'otro sitio' => 'https://ejemplo.com/panel/sesiones-apartadas',
    'sección de otro rol' => fn (): string => route('panel.bitacora'),
]);

// ---------------------------------------------------------------------
// El estado de los formatos abierto desde una sesión
// ---------------------------------------------------------------------

it('vuelve del estado de los formatos a los participantes de la sesión desde la que se abrió', function (): void {
    $sesion = Solicitud::factory()->aprobada()->create();

    $pagina = $this->actingAs($this->administrativo)
        ->get(route('panel.formatos-confidencialidad.estado', ['sesion' => $sesion->id]));

    $pagina->assertOk()->assertSee('Participantes de la sesión');
    expect(enlaceDeVuelta($pagina->getContent()))->toBe(route('panel.solicitudes.participantes', $sesion));
});

it('sin sesión, el estado de los formatos vuelve a la verificación', function (): void {
    $pagina = $this->actingAs($this->administrativo)->get(route('panel.formatos-confidencialidad.estado'));

    expect(enlaceDeVuelta($pagina->getContent()))->toBe(route('panel.formatos-confidencialidad'));
});

// ---------------------------------------------------------------------
// Pantallas que cuelgan de una lista con filtros
// ---------------------------------------------------------------------

it('vuelve de la edición de un ítem al inventario con los filtros que tenía', function (): void {
    $item = ItemInventario::factory()->create();
    $filtrado = route('panel.inventario', ['buscar' => 'sonda']);

    $pagina = $this->actingAs($this->administrativo)
        ->withHeader('referer', $filtrado)
        ->get(route('panel.inventario.editar', $item));

    expect(enlaceDeVuelta($pagina->getContent()))->toBe($filtrado);
});

it('al guardar un ítem vuelve al inventario con los filtros que tenía', function (): void {
    $item = ItemInventario::factory()->create();
    $filtrado = route('panel.inventario', ['buscar' => 'sonda']);

    Livewire::actingAs($this->administrativo)
        ->test(FormularioItem::class, ['item' => $item, 'volverA' => $filtrado])
        ->call('guardar')
        ->assertRedirect($filtrado);
});

it('la vuelta al guardar no se puede cambiar desde el navegador', function (): void {
    $item = ItemInventario::factory()->create();

    Livewire::actingAs($this->administrativo)
        ->test(FormularioItem::class, ['item' => $item, 'volverA' => route('panel.inventario')])
        ->set('volverA', 'https://ejemplo.com');
})->throws(CannotUpdateLockedPropertyException::class);
