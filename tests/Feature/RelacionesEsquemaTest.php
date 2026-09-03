<?php

declare(strict_types=1);

use App\Enums\EstadoSolicitud;
use App\Enums\Rol;
use App\Enums\TipoItemInventario;
use App\Enums\TipoSesion;
use App\Models\CasoClinico;
use App\Models\ConsentimientoEstudiante;
use App\Models\ConsentimientoPlantilla;
use App\Models\Evaluacion;
use App\Models\ItemInventario;
use App\Models\Preparacion;
use App\Models\Solicitud;
use App\Models\TipoEvaluacion;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Database\QueryException;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
});

it('permite que un usuario tenga varios roles a la vez', function (): void {
    $usuario = User::factory()->create();

    $usuario->assignRole([Rol::Coordinador->value, Rol::Docente->value]);

    expect($usuario->hasRole(Rol::Coordinador->value))->toBeTrue()
        ->and($usuario->hasRole(Rol::Docente->value))->toBeTrue()
        ->and($usuario->roles)->toHaveCount(2)
        ->and($usuario->hasRole(Rol::Estudiante->value))->toBeFalse();
});

it('devuelve los items de inventario de un caso clínico con su cantidad', function (): void {
    $caso = CasoClinico::factory()->create();
    $simulador = ItemInventario::factory()->simulador()->create(['nombre' => 'Maniquí de parto']);
    $gasas = ItemInventario::factory()->equipoBasico()->create(['nombre' => 'Gasas estériles']);

    $caso->items()->attach($simulador->id, ['cantidad' => 1]);
    $caso->items()->attach($gasas->id, ['cantidad' => 15]);

    $items = $caso->fresh()->items;

    expect($items)->toHaveCount(2)
        ->and($items->firstWhere('nombre', 'Maniquí de parto')->pivot->cantidad)->toBe(1)
        ->and($items->firstWhere('nombre', 'Gasas estériles')->pivot->cantidad)->toBe(15)
        ->and($items->firstWhere('nombre', 'Maniquí de parto')->tipo)->toBe(TipoItemInventario::Simulador);
});

it('deja una solicitud aprobada con exactamente una preparación', function (): void {
    $solicitud = Solicitud::factory()->aprobada()->create();

    Preparacion::factory()->create(['solicitud_id' => $solicitud->id]);

    expect($solicitud->estado)->toBe(EstadoSolicitud::Aprobada)
        ->and($solicitud->fresh()->preparacion)->not->toBeNull()
        ->and(Preparacion::where('solicitud_id', $solicitud->id)->count())->toBe(1);
});

it('rechaza una segunda preparación para la misma solicitud', function (): void {
    $solicitud = Solicitud::factory()->aprobada()->create();
    Preparacion::factory()->create(['solicitud_id' => $solicitud->id]);

    expect(fn () => Preparacion::factory()->create(['solicitud_id' => $solicitud->id]))
        ->toThrow(QueryException::class);
});

it('no permite crear una evaluación sin solicitud_id', function (): void {
    $tipo = TipoEvaluacion::factory()->create();
    $docente = User::factory()->create();

    expect(fn () => Evaluacion::create([
        'tipo_evaluacion_id' => $tipo->id,
        'docente_id' => $docente->id,
    ]))->toThrow(QueryException::class);
});

it('no permite dos evaluaciones sobre la misma solicitud', function (): void {
    $solicitud = Solicitud::factory()->deEvaluacion()->aprobada()->create();
    Evaluacion::factory()->create(['solicitud_id' => $solicitud->id]);

    expect(fn () => Evaluacion::factory()->create(['solicitud_id' => $solicitud->id]))
        ->toThrow(QueryException::class);
});

it('aplica el índice único de consentimiento por estudiante y periodo', function (): void {
    $estudiante = User::factory()->estudiante()->create();
    $plantilla = ConsentimientoPlantilla::factory()->create();

    ConsentimientoEstudiante::factory()->create([
        'estudiante_id' => $estudiante->id,
        'plantilla_id' => $plantilla->id,
        'periodo_academico' => '2026-2',
    ]);

    expect(fn () => ConsentimientoEstudiante::factory()->create([
        'estudiante_id' => $estudiante->id,
        'plantilla_id' => $plantilla->id,
        'periodo_academico' => '2026-2',
    ]))->toThrow(QueryException::class);
});

it('permite un consentimiento nuevo al cambiar de periodo académico', function (): void {
    $estudiante = User::factory()->estudiante()->create();
    $plantilla = ConsentimientoPlantilla::factory()->create();

    foreach (['2026-1', '2026-2', '2027-1'] as $periodo) {
        ConsentimientoEstudiante::factory()->create([
            'estudiante_id' => $estudiante->id,
            'plantilla_id' => $plantilla->id,
            'periodo_academico' => $periodo,
        ]);
    }

    expect($estudiante->consentimientos)->toHaveCount(3)
        ->and(ConsentimientoEstudiante::delPeriodo('2026-2')->where('estudiante_id', $estudiante->id)->count())->toBe(1);
});

it('encadena solicitud, preparación y evaluación sobre el mismo escenario', function (): void {
    $solicitud = Solicitud::factory()->deEvaluacion()->aprobada()->create();
    Preparacion::factory()->preparada()->create(['solicitud_id' => $solicitud->id]);
    $evaluacion = Evaluacion::factory()->create(['solicitud_id' => $solicitud->id]);

    $solicitud = $solicitud->fresh();

    expect($solicitud->tipo)->toBe(TipoSesion::Evaluacion)
        ->and($solicitud->preparacion->sala)->not->toBeNull()
        ->and($solicitud->evaluacion->id)->toBe($evaluacion->id)
        ->and($evaluacion->solicitud->id)->toBe($solicitud->id);
});
