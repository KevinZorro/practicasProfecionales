<?php

declare(strict_types=1);

use App\Exceptions\SesionQueSeCruza;
use App\Models\EstudianteDeLaSesion;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\SolicitudService;
use Database\Seeders\RolSeeder;
use Illuminate\Database\QueryException;

/*
 * Nadie en dos sesiones a la vez (decisión del 2026-11-07), con dos
 * personas actuando a la vez: dos docentes completan la lista de sus
 * sesiones, que se cruzan, con la misma estudiante. Cada uno comprueba que
 * ella esté libre antes de que el otro la escriba, y sin bloqueo los dos la
 * agregan.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
});

/** Lo que hace el otro docente, desde su propia sesión de base de datos. */
function agregarAOtraSesion(int $solicitudId, int $estudianteId, int $docenteId): void
{
    enOtraSesion(fn () => app(SolicitudService::class)->agregarEstudiantes(
        Solicitud::findOrFail($solicitudId),
        [$estudianteId],
        User::findOrFail($docenteId),
    ));
}

it('dos docentes que agregan a la vez a la misma estudiante a sesiones que se cruzan no la dejan en las dos', function (): void {
    $fecha = now()->addWeek()->toDateString();
    $docente = User::factory()->docente()->create();
    $otroDocente = User::factory()->docente()->create();
    $suya = Solicitud::factory()->aprobada()->create(['docente_id' => $docente->id, 'fecha' => $fecha, 'hora_inicio' => '08:00', 'hora_fin' => '10:00']);
    $delOtro = Solicitud::factory()->aprobada()->create(['docente_id' => $otroDocente->id, 'fecha' => $fecha, 'hora_inicio' => '09:00', 'hora_fin' => '11:00']);
    $ana = User::factory()->estudiante()->create();

    $seInterpuso = false;

    // Justo antes de que se escriba a Ana en la sesión del docente, el otro
    // docente la agrega a la suya.
    EstudianteDeLaSesion::creating(function (EstudianteDeLaSesion $fila) use (&$seInterpuso, $suya, $delOtro, $ana, $otroDocente): void {
        if ($seInterpuso || $fila->estudiante_id !== $ana->id || $fila->solicitud_id !== $suya->id) {
            return;
        }

        $seInterpuso = true;

        try {
            agregarAOtraSesion($delOtro->id, $ana->id, $otroDocente->id);
        } catch (SesionQueSeCruza|QueryException) {
            // Si Ana está protegida, el otro docente no entra: la ve ocupada
            // o se cansa de esperar al primero.
        }
    });

    app(SolicitudService::class)->agregarEstudiantes($suya, [$ana->id], $docente);

    // El otro docente vuelve a intentarlo cuando el primero ya terminó.
    try {
        agregarAOtraSesion($delOtro->id, $ana->id, $otroDocente->id);
    } catch (SesionQueSeCruza) {
        // Lo esperado: Ana ya está en una sesión que se cruza.
    }

    $sesionesDeAna = EstudianteDeLaSesion::query()->where('estudiante_id', $ana->id)->whereNull('retirado_at')->count();

    expect($seInterpuso)->toBeTrue()
        ->and($sesionesDeAna)->toBe(1);
});
