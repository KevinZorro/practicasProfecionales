<?php

declare(strict_types=1);

use App\Enums\AccionAuditada;
use App\Enums\Reporte;
use App\Models\ListaDeReposicion;
use App\Models\RegistroDeBitacora;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Facades\Excel;

/*
 * B4.2: un PDF o un Excel con nombres y resultados circula sin la pantalla
 * que lo generó. La Ley 1581 y un incidente preguntan quién lo sacó, cuándo
 * y con qué filtros, así que cada exportación queda en la bitácora. Solo la
 * que ocurre: la negada ya queda como acceso negado, y la que no se genera
 * no deja nada.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->coordinadora = User::factory()->coordinador()->create();
});

/** @return Collection<int, RegistroDeBitacora> */
function exportacionesEnLaBitacora(): Collection
{
    return RegistroDeBitacora::query()->where('accion', AccionAuditada::ReporteExportado)->get();
}

it('deja en la bitácora quién exportó qué reporte, en qué formato y con qué filtros', function (string $formato, string $etiqueta): void {
    Excel::fake();

    $this->actingAs($this->coordinadora)
        ->get(route("panel.reportes.{$formato}", ['reporte' => Reporte::ResultadosDeEvaluacion, 'desde' => '2026-04-01', 'hasta' => '2026-04-30']))
        ->assertOk();

    $rastro = exportacionesEnLaBitacora();

    expect($rastro)->toHaveCount(1)
        ->and($rastro->first()->user_id)->toBe($this->coordinadora->id)
        ->and($rastro->first()->descripcion)->toContain($etiqueta)
        ->and($rastro->first()->descripcion)->toContain(Reporte::ResultadosDeEvaluacion->titulo())
        ->and($rastro->first()->descripcion)->toContain('Del 2026-04-01 al 2026-04-30');
})->with([
    'Excel' => ['excel', 'Excel'],
    'PDF' => ['pdf', 'PDF'],
]);

it('deja en la bitácora lo que exporta un docente supervisor', function (): void {
    Excel::fake();
    abrirPeriodo();
    $supervisor = User::factory()->supervisor()->create();

    $this->actingAs($supervisor)->get(route('panel.supervision.excel'))->assertOk();

    expect(exportacionesEnLaBitacora())->toHaveCount(1)
        ->and(exportacionesEnLaBitacora()->first()->user_id)->toBe($supervisor->id);
});

it('deja en la bitácora la descarga de una lista de reposición cerrada', function (): void {
    Excel::fake();
    $lista = ListaDeReposicion::factory()->cerrada()->create();

    $this->actingAs($this->coordinadora)->get(route('panel.reposicion.excel', $lista))->assertOk();

    expect(exportacionesEnLaBitacora())->toHaveCount(1);
});

it('no deja rastro de una exportación que no se generó', function (): void {
    $this->actingAs($this->coordinadora)
        ->get(route('panel.reportes.pdf', ['reporte' => Reporte::ResultadosDeEvaluacion, 'desde' => '2026-01-01', 'hasta' => '2026-12-31']))
        ->assertStatus(422);

    expect(exportacionesEnLaBitacora())->toBeEmpty();
});
