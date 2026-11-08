<?php

declare(strict_types=1);

use App\Exceptions\CapacidadDeEstudiantesExcedida;
use App\Models\CasoClinico;
use App\Models\EstudianteDeLaSesion;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\SolicitudService;
use Database\Seeders\RolSeeder;
use Illuminate\Database\QueryException;

/*
 * B3.11 de la auditoría, el tope del escenario (RF74): el docente y un
 * administrativo completan a la vez la lista de una sesión cuyo escenario
 * admite dos estudiantes y ya tiene uno. Cada uno cuenta 1 + 1 = 2, dentro
 * del tope, y agrega el suyo.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
});

/** Lo que hace el administrativo, desde su propia sesión de base de datos. */
function agregarDesdeOtraSesion(int $solicitudId, int $estudianteId, int $actorId): void
{
    enOtraSesion(fn () => app(SolicitudService::class)->agregarEstudiantes(
        Solicitud::findOrFail($solicitudId),
        [$estudianteId],
        User::findOrFail($actorId),
    ));
}

it('el docente y un administrativo que completan a la vez la lista no pasan del tope del escenario', function (): void {
    $caso = CasoClinico::factory()->create(['capacidad_maxima_estudiantes' => 2]);
    $docente = User::factory()->docente()->create();
    $administrativo = User::factory()->administrativo()->create();
    $solicitud = Solicitud::factory()->aprobada()->create([
        'docente_id' => $docente->id,
        'caso_clinico_id' => $caso->id,
        'fecha' => now()->addWeek()->toDateString(),
    ]);
    $solicitud->estudiantes()->attach(User::factory()->estudiante()->create());
    $delDocente = User::factory()->estudiante()->create();
    $delAdministrativo = User::factory()->estudiante()->create();

    $seInterpuso = false;

    // Justo antes de que se escriba el estudiante del docente, entra el administrativo.
    EstudianteDeLaSesion::creating(function (EstudianteDeLaSesion $fila) use (&$seInterpuso, $solicitud, $delDocente, $delAdministrativo, $administrativo): void {
        if ($seInterpuso || $fila->estudiante_id !== $delDocente->id) {
            return;
        }

        $seInterpuso = true;

        try {
            agregarDesdeOtraSesion($solicitud->id, $delAdministrativo->id, $administrativo->id);
        } catch (CapacidadDeEstudiantesExcedida|QueryException) {
            // Si la lista está protegida, el administrativo no entra: ve el
            // cupo lleno o se cansa de esperar al docente.
        }
    });

    app(SolicitudService::class)->agregarEstudiantes($solicitud, [$delDocente->id], $docente);

    // El administrativo vuelve a intentarlo cuando el docente ya terminó.
    try {
        agregarDesdeOtraSesion($solicitud->id, $delAdministrativo->id, $administrativo->id);
    } catch (CapacidadDeEstudiantesExcedida) {
        // Lo esperado: el cupo ya está lleno.
    }

    expect($seInterpuso)->toBeTrue()
        ->and($solicitud->estudiantesPresentes()->count())->toBe(2);
});
