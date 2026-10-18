<?php

declare(strict_types=1);

use App\Enums\EstadoPreparacion;
use App\Enums\TipoSesion;
use App\Exceptions\NovedadInvalida;
use App\Exceptions\SolicitudInvalida;
use App\Livewire\Evaluacion\RegistroDeEvaluacion;
use App\Livewire\Preparacion\TableroDiario;
use App\Livewire\Solicitud\FormularioSolicitud;
use App\Livewire\Solicitud\SesionesApartadas;
use App\Models\CasoClinico;
use App\Models\Evaluacion;
use App\Models\ItemInventario;
use App\Models\Materia;
use App\Models\Preparacion;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\DatosNuevaSolicitud;
use App\Services\DatosReprogramacion;
use App\Services\DatosSesionApartada;
use App\Services\NovedadesDeSesionService;
use App\Services\RegistroPrevioService;
use App\Services\SolicitudService;
use Database\Seeders\RolSeeder;
use Livewire\Livewire;

/*
 * QA-15: lo que llega por la petición puede no ser lo que la pantalla
 * ofrece. Un valor fuera de lo permitido es un error de validación o una
 * regla de dominio, nunca un 500.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->docente = User::factory()->docente()->create();
    $this->administrativo = User::factory()->administrativo()->create();
    $this->materia = Materia::factory()->create();
    $this->caso = CasoClinico::factory()->create(['nombre' => 'Atención de parto']);
    $this->caso->materias()->attach($this->materia->id);
});

function datosDeLaSolicitud(Materia $materia, CasoClinico $caso, array $cambios = []): DatosNuevaSolicitud
{
    return new DatosNuevaSolicitud(...[
        'materiaId' => $materia->id,
        'casoClinicoId' => $caso->id,
        'tipo' => TipoSesion::Practica,
        'fecha' => now()->addWeek()->format('Y-m-d'),
        'horaInicio' => '07:00',
        'horaFin' => '09:00',
        'grupo' => 'A',
        'estudianteIds' => idsDeEstudiantes(2),
        ...$cambios,
    ]);
}

// ---------------------------------------------------------------------
// Caso clínico y materia (RF24)
// ---------------------------------------------------------------------

it('no registra un caso clínico en una materia a la que no está asociado', function (): void {
    $otraMateria = Materia::factory()->create();

    expect(fn () => app(SolicitudService::class)->crear($this->docente, datosDeLaSolicitud($otraMateria, $this->caso)))
        ->toThrow(SolicitudInvalida::class, 'no está asociado');
    expect(Solicitud::query()->count())->toBe(0);
});

it('registra el caso en su materia', function (): void {
    expect(app(SolicitudService::class)->crear($this->docente, datosDeLaSolicitud($this->materia, $this->caso)))
        ->toBeInstanceOf(Solicitud::class);
});

it('tampoco lo registra como sesión apartada ni lo reprograma a un caso de otra materia', function (): void {
    $ajeno = CasoClinico::factory()->create();

    expect(fn () => app(RegistroPrevioService::class)->registrar(new DatosSesionApartada(
        docenteId: $this->docente->id,
        materiaId: $this->materia->id,
        casoClinicoId: $ajeno->id,
        tipo: TipoSesion::Practica,
        fecha: now()->addWeek()->format('Y-m-d'),
        horaInicio: '07:00',
        horaFin: '09:00',
        cantidadEstudiantes: 4,
    ), $this->administrativo))->toThrow(SolicitudInvalida::class, 'no está asociado');

    $sesion = Solicitud::factory()->aprobada()->create(['docente_id' => $this->docente->id, 'materia_id' => $this->materia->id, 'caso_clinico_id' => $this->caso->id, 'fecha' => now()->addWeek()->format('Y-m-d'), 'cantidad_estudiantes' => 3]);
    Preparacion::factory()->create(['solicitud_id' => $sesion->id]);

    expect(fn () => app(NovedadesDeSesionService::class)->reprogramar($sesion, new DatosReprogramacion(
        fecha: $sesion->fecha->format('Y-m-d'),
        horaInicio: substr($sesion->hora_inicio, 0, 5),
        horaFin: substr($sesion->hora_fin, 0, 5),
        casoClinicoId: $ajeno->id,
        motivo: 'x',
        constanciaComunicacion: 'y',
    ), $this->administrativo))->toThrow(NovedadInvalida::class, 'no está asociado');
});

it('el formulario ofrece solo los casos de la materia elegida', function (): void {
    CasoClinico::factory()->create(['nombre' => 'Caso de otra materia']);

    Livewire::actingAs($this->docente)
        ->test(FormularioSolicitud::class)
        ->set('materiaId', $this->materia->id)
        ->assertSee('Atención de parto')
        ->assertDontSee('Caso de otra materia');
});

// ---------------------------------------------------------------------
// Valores fuera de lo permitido
// ---------------------------------------------------------------------

it('el formulario rechaza un tipo de sesión que no existe, sin error del servidor', function (): void {
    Livewire::actingAs($this->docente)
        ->test(FormularioSolicitud::class)
        ->set('tipo', 'inventado')
        ->call('guardar')
        ->assertHasErrors(['tipo']);

    Livewire::actingAs($this->administrativo)
        ->test(SesionesApartadas::class)
        ->set('tipo', 'inventado')
        ->call('registrar')
        ->assertHasErrors(['tipo']);
});

it('el formulario no acepta materias ni casos inactivos', function (): void {
    $materiaInactiva = Materia::factory()->create(['activo' => false]);
    $casoInactivo = CasoClinico::factory()->create(['activo' => false]);

    Livewire::actingAs($this->docente)
        ->test(FormularioSolicitud::class)
        ->set('materiaId', $materiaInactiva->id)
        ->set('casoClinicoId', $casoInactivo->id)
        ->call('guardar')
        ->assertHasErrors(['materiaId', 'casoClinicoId']);

    Livewire::actingAs($this->administrativo)
        ->test(SesionesApartadas::class)
        ->set('materiaId', $materiaInactiva->id)
        ->set('casoClinicoId', $casoInactivo->id)
        ->call('registrar')
        ->assertHasErrors(['materiaId', 'casoClinicoId']);
});

it('rechaza equipos que no existen o cantidades descabelladas', function (): void {
    $item = ItemInventario::factory()->create();

    expect(fn () => app(SolicitudService::class)->crear($this->docente, datosDeLaSolicitud($this->materia, $this->caso, ['items' => [9999999 => 1]])))
        ->toThrow(SolicitudInvalida::class, 'inventario');

    Livewire::actingAs($this->docente)
        ->test(FormularioSolicitud::class)
        ->set('materiaId', $this->materia->id)
        ->set('casoClinicoId', $this->caso->id)
        ->set('items', [$item->id => 999999])
        ->call('guardar')
        ->assertHasErrors(['items.'.$item->id]);
});

it('no registra una sesión apartada a nombre de un docente inactivo', function (): void {
    $inactivo = User::factory()->docente()->inactivo()->create();

    expect(fn () => app(RegistroPrevioService::class)->registrar(new DatosSesionApartada(
        docenteId: $inactivo->id,
        materiaId: $this->materia->id,
        casoClinicoId: $this->caso->id,
        tipo: TipoSesion::Practica,
        fecha: now()->addWeek()->format('Y-m-d'),
        horaInicio: '07:00',
        horaFin: '09:00',
        cantidadEstudiantes: 4,
    ), $this->administrativo))->toThrow(SolicitudInvalida::class, 'docente');
});

it('ignora un resultado de evaluación o un estado de preparación inventados', function (): void {
    $evaluacion = Evaluacion::factory()->create(['docente_id' => $this->docente->id]);
    $registro = $evaluacion->estudiantes()->create(['estudiante_id' => User::factory()->estudiante()->create()->id, 'intento' => 1]);

    Livewire::actingAs($this->docente)
        ->test(RegistroDeEvaluacion::class, ['evaluacion' => $evaluacion])
        ->call('resultado', $registro->id, 'inventado')
        ->assertOk();
    expect($registro->fresh()->resultado)->toBeNull();

    $preparacion = Preparacion::factory()->create(['estado' => EstadoPreparacion::Pendiente]);

    Livewire::actingAs($this->administrativo)
        ->test(TableroDiario::class)
        ->call('cambiarEstado', $preparacion->id, 'inventado')
        ->assertOk();
    expect($preparacion->fresh()->estado)->toBe(EstadoPreparacion::Pendiente);
});
