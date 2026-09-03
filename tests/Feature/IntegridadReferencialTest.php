<?php

declare(strict_types=1);

use App\Models\CasoClinico;
use App\Models\Evaluacion;
use App\Models\EvaluacionEstudiante;
use App\Models\ItemInventario;
use App\Models\Materia;
use App\Models\Preparacion;
use App\Models\Sala;
use App\Models\Solicitud;
use App\Models\Taller;
use App\Models\TipoEvaluacion;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Database\QueryException;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
});

it('no deja borrar inventario referenciado por un caso clínico', function (): void {
    $caso = CasoClinico::factory()->create();
    $item = ItemInventario::factory()->create();
    $caso->items()->attach($item->id, ['cantidad' => 2]);

    expect(fn () => $item->delete())->toThrow(QueryException::class);
});

it('no deja borrar una materia con solicitudes registradas', function (): void {
    $materia = Materia::factory()->create();
    Solicitud::factory()->create(['materia_id' => $materia->id]);

    expect(fn () => $materia->delete())->toThrow(QueryException::class);
});

it('no deja borrar una solicitud con evaluación registrada', function (): void {
    $solicitud = Solicitud::factory()->deEvaluacion()->aprobada()->create();
    Evaluacion::factory()->create(['solicitud_id' => $solicitud->id]);

    expect(fn () => $solicitud->delete())->toThrow(QueryException::class);
});

it('no deja borrar una sala usada en una preparación', function (): void {
    $sala = Sala::factory()->create();
    Preparacion::factory()->create(['sala_id' => $sala->id]);

    expect(fn () => $sala->delete())->toThrow(QueryException::class);
});

it('arrastra la preparación al borrar su solicitud', function (): void {
    $solicitud = Solicitud::factory()->aprobada()->create();
    $preparacion = Preparacion::factory()->create(['solicitud_id' => $solicitud->id]);

    $solicitud->delete();

    expect(Preparacion::find($preparacion->id))->toBeNull();
});

it('arrastra los ítems y los resultados al borrar una evaluación', function (): void {
    $evaluacion = Evaluacion::factory()->create();
    $item = $evaluacion->items()->create(['descripcion' => 'Realiza higiene de manos', 'orden' => 1]);
    $registro = EvaluacionEstudiante::factory()->create([
        'evaluacion_id' => $evaluacion->id,
        'estudiante_id' => User::factory()->estudiante(),
    ]);
    $registro->items()->attach($item->id, ['cumplido' => true]);

    $evaluacion->delete();

    expect(EvaluacionEstudiante::find($registro->id))->toBeNull()
        ->and(DB::table('evaluacion_estudiante_item')->count())->toBe(0);
});

it('borra en cascada el checklist plantilla al borrar su tipo de evaluación', function (): void {
    $tipo = TipoEvaluacion::factory()->create();
    $tipo->itemsChecklist()->create(['descripcion' => 'Verifica la identidad del paciente', 'orden' => 1]);

    $tipo->delete();

    expect(DB::table('items_checklist')->count())->toBe(0);
});

it('conserva los contactos recibidos al intentar borrar su taller', function (): void {
    $taller = Taller::factory()->create();
    $taller->solicitudesInformacion()->createQuietly([
        'nombre' => 'Persona interesada',
        'email' => 'interesada@ejemplo.com',
    ]);

    expect(fn () => $taller->delete())->toThrow(QueryException::class);
});
