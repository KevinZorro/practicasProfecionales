<?php

declare(strict_types=1);

use App\Enums\TipoSesion;
use App\Exceptions\CapacidadDeEstudiantesExcedida;
use App\Livewire\Solicitud\FormularioSolicitud;
use App\Models\CasoClinico;
use App\Models\Materia;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\DatosNuevaSolicitud;
use App\Services\SolicitudService;
use Database\Seeders\RolSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->docente = User::factory()->docente()->create();
    $this->materia = Materia::factory()->create();
    $this->servicio = app(SolicitudService::class);
});

/** Datos de una solicitud para el caso dado, con la cantidad que se quiera. */
function solicitudPara(CasoClinico $caso, Materia $materia, int $estudiantes): DatosNuevaSolicitud
{
    return new DatosNuevaSolicitud(
        materiaId: $materia->id,
        casoClinicoId: $caso->id,
        tipo: TipoSesion::Practica,
        fecha: '2026-10-05',
        horaInicio: '08:00',
        horaFin: '10:00',
        cantidadEstudiantes: $estudiantes,
    );
}

it('deja crear una solicitud que cabe en el escenario', function (): void {
    $caso = CasoClinico::factory()->conCapacidad(7)->create();

    $solicitud = $this->servicio->crear($this->docente, solicitudPara($caso, $this->materia, 5));

    expect($solicitud->cantidad_estudiantes)->toBe(5);
});

it('deja crear una solicitud justo en el límite del escenario', function (): void {
    // La frontera es el sitio donde un "mayor o igual" mal puesto se cuela.
    $caso = CasoClinico::factory()->conCapacidad(7)->create();

    $solicitud = $this->servicio->crear($this->docente, solicitudPara($caso, $this->materia, 7));

    expect($solicitud->cantidad_estudiantes)->toBe(7);
});

it('no deja crear una solicitud por encima de la capacidad del escenario', function (): void {
    $caso = CasoClinico::factory()->conCapacidad(7)->create(['nombre' => 'Atención de parto normal']);

    expect(fn () => $this->servicio->crear($this->docente, solicitudPara($caso, $this->materia, 8)))
        ->toThrow(CapacidadDeEstudiantesExcedida::class);

    expect(Solicitud::count())->toBe(0);
});

it('dice en el mensaje cuál es el máximo y cuántos se pidieron', function (): void {
    $caso = CasoClinico::factory()->conCapacidad(7)->create(['nombre' => 'Herida por arma de fuego']);

    expect(fn () => $this->servicio->crear($this->docente, solicitudPara($caso, $this->materia, 12)))
        ->toThrow(
            CapacidadDeEstudiantesExcedida::class,
            'El escenario "Herida por arma de fuego" admite 7 estudiantes como máximo y se solicitaron 12.',
        );
});

it('no limita un escenario al que todavía no le registraron capacidad', function (): void {
    // Bloquear por un campo que el ADMIN no ha llenado pararía clases
    // reales. Null se lee como "sin definir", no como "cero".
    $caso = CasoClinico::factory()->create(['capacidad_maxima_estudiantes' => null]);

    $solicitud = $this->servicio->crear($this->docente, solicitudPara($caso, $this->materia, 40));

    expect($solicitud->cantidad_estudiantes)->toBe(40);
});

it('respeta la capacidad de cada escenario por separado', function (): void {
    $morfofisiologia = CasoClinico::factory()->conCapacidad(15)->create();
    $trauma = CasoClinico::factory()->conCapacidad(7)->create();

    $solicitud = $this->servicio->crear($this->docente, solicitudPara($morfofisiologia, $this->materia, 15));

    expect($solicitud->cantidad_estudiantes)->toBe(15)
        ->and(fn () => $this->servicio->crear($this->docente, solicitudPara($trauma, $this->materia, 15)))
        ->toThrow(CapacidadDeEstudiantesExcedida::class);
});

// ---------------------------------------------------------------------
// El formulario del docente
// ---------------------------------------------------------------------

it('enseña el error junto al campo de cantidad y no registra nada', function (): void {
    $caso = CasoClinico::factory()->conCapacidad(7)->create();

    Livewire::actingAs($this->docente)
        ->test(FormularioSolicitud::class)
        ->set('materiaId', $this->materia->id)
        ->set('casoClinicoId', $caso->id)
        ->set('fecha', '2026-10-05')
        ->set('horaInicio', '08:00')
        ->set('horaFin', '10:00')
        ->set('cantidadEstudiantes', 20)
        ->call('guardar')
        ->assertHasErrors('cantidadEstudiantes');

    expect(Solicitud::count())->toBe(0);
});

it('avisa del máximo del escenario en cuanto el docente lo elige', function (): void {
    $caso = CasoClinico::factory()->conCapacidad(7)->create();

    Livewire::actingAs($this->docente)
        ->test(FormularioSolicitud::class)
        ->set('casoClinicoId', $caso->id)
        ->assertSet('capacidadDelCaso', 7)
        ->assertSee('admite 7 estudiantes como máximo');
});

it('no avisa de ningún máximo si el escenario no tiene capacidad registrada', function (): void {
    $caso = CasoClinico::factory()->create(['capacidad_maxima_estudiantes' => null]);

    Livewire::actingAs($this->docente)
        ->test(FormularioSolicitud::class)
        ->set('casoClinicoId', $caso->id)
        ->assertSet('capacidadDelCaso', null)
        ->assertDontSee('estudiantes como máximo');
});
