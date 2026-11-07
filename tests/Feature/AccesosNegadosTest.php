<?php

declare(strict_types=1);

use App\Enums\AccionAuditada;
use App\Enums\Reporte;
use App\Models\Evaluacion;
use App\Models\FormatoConfidencialidad;
use App\Models\ItemInventario;
use App\Models\ListaDeReposicion;
use App\Models\PlantillaConfidencialidad;
use App\Models\RegistroDeBitacora;
use App\Models\Solicitud;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route as Ruta;
use Illuminate\Support\Facades\Route;

/*
 * Escenario 1 de la auditoría (B4.5): quien pide con un ID algo que no le
 * corresponde recibe un 403 y deja rastro en la bitácora: quién, qué y
 * cuándo. Es lo que pregunta un incidente o una consulta de la Ley 1581, y
 * lo que delata a quien prueba IDs.
 *
 * Una ruta nueva del panel que recibe un ID entra en esta batería, o la
 * prueba de cobertura de abajo falla.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    abrirPeriodo();
    $this->duena = User::factory()->docente()->create();
    $this->intrusa = User::factory()->docente()->create();
});

/**
 * Cada ruta del panel con un ID, y cómo pedir con ella algo que no es de la
 * intrusa: devuelve la URL y el registro pedido, si la ruta lo resuelve.
 *
 * @return array<string, Closure(User): array{string, Model|null}>
 */
function rutasDelPanelConId(): array
{
    $deLaSolicitud = static function (string $ruta): Closure {
        return static function (User $duena) use ($ruta): array {
            $solicitud = Solicitud::factory()->create(['docente_id' => $duena->id]);

            return [route($ruta, $solicitud), $solicitud];
        };
    };

    $deLaListaCerrada = static function (string $ruta): Closure {
        return static function () use ($ruta): array {
            $lista = ListaDeReposicion::factory()->cerrada()->create();

            return [route($ruta, $lista), $lista];
        };
    };

    return [
        'panel.solicitudes.ver' => $deLaSolicitud('panel.solicitudes.ver'),
        'panel.solicitudes.participantes' => $deLaSolicitud('panel.solicitudes.participantes'),
        'panel.solicitudes.novedades' => $deLaSolicitud('panel.solicitudes.novedades'),
        'panel.solicitudes.formato-intramural' => $deLaSolicitud('panel.solicitudes.formato-intramural'),
        'panel.evaluaciones.registro' => static function (User $duena): array {
            $evaluacion = Evaluacion::factory()->create(['docente_id' => $duena->id]);

            return [route('panel.evaluaciones.registro', $evaluacion), $evaluacion];
        },
        'panel.formatos-confidencialidad.firmado' => static function (User $duena): array {
            $formato = FormatoConfidencialidad::factory()->cargado()->create(['firmante_id' => $duena->id]);

            return [route('panel.formatos-confidencialidad.firmado', $formato), $formato];
        },
        'panel.formatos-confidencialidad.version' => static function (): array {
            $plantilla = PlantillaConfidencialidad::factory()->create();

            return [route('panel.formatos-confidencialidad.version', $plantilla), $plantilla];
        },
        'panel.inventario.editar' => static function (): array {
            $item = ItemInventario::factory()->create();

            return [route('panel.inventario.editar', $item), $item];
        },
        'panel.reposicion.excel' => $deLaListaCerrada('panel.reposicion.excel'),
        'panel.reposicion.pdf' => $deLaListaCerrada('panel.reposicion.pdf'),
        'panel.reportes.excel' => static fn (): array => [route('panel.reportes.excel', Reporte::ResultadosDeEvaluacion->value), null],
        'panel.reportes.pdf' => static fn (): array => [route('panel.reportes.pdf', Reporte::ResultadosDeEvaluacion->value), null],
        'panel.usuarios.editar' => static function (User $duena): array {
            return [route('panel.usuarios.editar', $duena), $duena];
        },
        'panel.usuarios.supervision' => static function (): array {
            $supervisor = User::factory()->supervisor()->create();

            return [route('panel.usuarios.supervision', $supervisor), $supervisor];
        },
    ];
}

dataset('rutas del panel con id', static function (): Generator {
    foreach (rutasDelPanelConId() as $nombre => $preparar) {
        yield $nombre => [$preparar];
    }
});

/** @return Illuminate\Database\Eloquent\Collection<int, RegistroDeBitacora> */
function accesosNegadosEnLaBitacora(): Illuminate\Database\Eloquent\Collection
{
    return RegistroDeBitacora::query()->where('accion', AccionAuditada::AccesoNegado)->get();
}

it('niega con 403 y deja en la bitácora quién pidió qué', function (Closure $preparar): void {
    [$url, $pedido] = $preparar($this->duena);

    $this->actingAs($this->intrusa)->get($url)->assertForbidden();

    $rastro = accesosNegadosEnLaBitacora();

    expect($rastro)->toHaveCount(1)
        ->and($rastro->first()->user_id)->toBe($this->intrusa->id)
        ->and($rastro->first()->descripcion)->toContain((string) parse_url($url, PHP_URL_PATH));

    if ($pedido instanceof Model) {
        expect($rastro->first()->auditable_type)->toBe($pedido->getMorphClass())
            ->and($rastro->first()->auditable_id)->toBe($pedido->getKey());
    }
})->with('rutas del panel con id');

it('cubre cada ruta del panel que recibe un ID', function (): void {
    $conId = collect(Route::getRoutes()->getRoutes())
        ->filter(static fn (Ruta $ruta): bool => str_starts_with((string) $ruta->getName(), 'panel.') && str_contains($ruta->uri(), '{'))
        ->map(static fn (Ruta $ruta): string => (string) $ruta->getName())
        ->values()
        ->all();

    expect(array_keys(rutasDelPanelConId()))->toEqualCanonicalizing($conId);
});

it('deja rastro de una acción de Livewire negada, con el ID que se mandó', function (): void {
    $ajena = Solicitud::factory()->deEvaluacion()->aprobada()->create(['docente_id' => $this->duena->id]);
    $this->actingAs($this->intrusa);

    accionDeLivewire(instantaneaDeLaPantalla(route('panel.evaluaciones')), 'registrar', [$ajena->id])
        ->assertForbidden();

    $rastro = accesosNegadosEnLaBitacora();

    expect($rastro)->toHaveCount(1)
        ->and($rastro->first()->user_id)->toBe($this->intrusa->id)
        ->and($rastro->first()->descripcion)->toContain('registrar')
        ->and($rastro->first()->descripcion)->toContain((string) $ajena->id);
});

it('no deja rastro de lo que sí se puede ver ni de un ID que no existe', function (): void {
    $propia = Solicitud::factory()->create(['docente_id' => $this->duena->id]);

    $this->actingAs($this->duena)->get(route('panel.solicitudes.ver', $propia))->assertOk();
    $this->get('/panel/solicitudes/999999999')->assertNotFound();

    expect(accesosNegadosEnLaBitacora())->toBeEmpty();
});
