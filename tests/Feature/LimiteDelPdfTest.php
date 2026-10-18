<?php

declare(strict_types=1);

use App\Livewire\Reportes\PantallaDeReportes;
use App\Models\CasoClinico;
use App\Models\Materia;
use App\Models\Preparacion;
use App\Models\Sala;
use App\Models\Solicitud;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Livewire\Livewire;

/*
 * QA-01: dompdf gasta cerca de medio megabyte por fila, así que un PDF de un
 * semestre agota la memoria de PHP. El PDF tiene tope de días y de filas; el
 * Excel no, y es la salida para los rangos grandes.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->coordinadora = User::factory()->coordinador()->create();
});

function sesionesAprobadasEn(string $fecha, int $cantidad): void
{
    $sala = Sala::factory()->create();
    $materia = Materia::factory()->create();
    $caso = CasoClinico::factory()->create();

    for ($i = 0; $i < $cantidad; $i++) {
        // Un docente por sesión: el reporte agrupa por docente y materia.
        $docente = User::factory()->docente()->create();
        $solicitud = Solicitud::factory()->aprobada()->create([
            'docente_id' => $docente->id,
            'materia_id' => $materia->id,
            'caso_clinico_id' => $caso->id,
            'fecha' => $fecha,
        ]);
        Preparacion::factory()->create(['solicitud_id' => $solicitud->id, 'sala_id' => $sala->id]);
    }
}

it('no genera el PDF de un rango mayor al tope de días y manda al Excel', function (): void {
    config(['laboratorio.reportes.pdf_dias_maximos' => 31]);
    sesionesAprobadasEn('2026-04-10', 1);

    $this->actingAs($this->coordinadora)
        ->get(route('panel.reportes.pdf', ['reporte' => 'uso_de_escenarios', 'desde' => '2026-04-01', 'hasta' => '2026-08-31']))
        ->assertStatus(422)
        ->assertSee('31 días', false)
        ->assertSee('Excel', false);
});

it('no genera el PDF sin rango de fechas', function (): void {
    sesionesAprobadasEn('2026-04-10', 1);

    $this->actingAs($this->coordinadora)
        ->get(route('panel.reportes.pdf', ['reporte' => 'uso_de_escenarios']))
        ->assertStatus(422);
});

it('genera el PDF de un rango de exactamente el tope de días', function (): void {
    config(['laboratorio.reportes.pdf_dias_maximos' => 31]);
    sesionesAprobadasEn('2026-04-10', 1);

    $this->actingAs($this->coordinadora)
        ->get(route('panel.reportes.pdf', ['reporte' => 'uso_de_escenarios', 'desde' => '2026-04-01', 'hasta' => '2026-05-01']))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('no genera el PDF si el rango, aun corto, trae más filas que el tope', function (): void {
    config(['laboratorio.reportes.pdf_filas_maximas' => 2]);
    sesionesAprobadasEn('2026-04-10', 3);

    $this->actingAs($this->coordinadora)
        ->get(route('panel.reportes.pdf', ['reporte' => 'uso_de_escenarios', 'desde' => '2026-04-01', 'hasta' => '2026-04-30']))
        ->assertStatus(422)
        ->assertSee('Excel', false);
});

it('el Excel no tiene tope de días', function (): void {
    sesionesAprobadasEn('2026-04-10', 1);

    $this->actingAs($this->coordinadora)
        ->get(route('panel.reportes.excel', ['reporte' => 'uso_de_escenarios', 'desde' => '2026-01-01', 'hasta' => '2026-12-31']))
        ->assertOk();
});

it('la pantalla ofrece el PDF solo cuando el rango cabe, y explica por qué no', function (): void {
    config(['laboratorio.reportes.pdf_dias_maximos' => 31]);

    Livewire::withQueryParams(['desde' => '2026-04-01', 'hasta' => '2026-04-30'])
        ->actingAs($this->coordinadora)->test(PantallaDeReportes::class)
        ->assertSee('Descargar PDF');

    Livewire::withQueryParams(['desde' => '2026-01-01', 'hasta' => '2026-12-31'])
        ->actingAs($this->coordinadora)->test(PantallaDeReportes::class)
        ->assertDontSee('Descargar PDF')
        ->assertSee('Descargar Excel')
        ->assertSee('31 días');
});

it('construye un PDF de cientos de filas sin agotar la memoria', function (): void {
    config(['laboratorio.reportes.pdf_filas_maximas' => 500]);
    sesionesAprobadasEn('2026-04-10', 150);
    $antes = memory_get_peak_usage(true);

    $this->actingAs($this->coordinadora)
        ->get(route('panel.reportes.pdf', ['reporte' => 'uso_de_escenarios', 'desde' => '2026-04-01', 'hasta' => '2026-04-30']))
        ->assertOk();

    expect(memory_get_peak_usage(true) - $antes)->toBeLessThan(128 * 1024 * 1024);
});
