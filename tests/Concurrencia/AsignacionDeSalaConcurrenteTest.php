<?php

declare(strict_types=1);

use App\Events\SalaAsignada;
use App\Exceptions\SalaOcupada;
use App\Models\Preparacion;
use App\Models\Sala;
use App\Models\Solicitud;
use App\Services\PreparacionService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;

/*
 * B3.11 de la auditoría: dos administrativos con el tablero abierto asignan
 * la misma sala, en el mismo instante, a dos montajes cuyas franjas se
 * cruzan. En producción son dos peticiones en dos procesos de PHP-FPM, cada
 * una con su sesión de PostgreSQL; aquí, dos conexiones a la misma base.
 *
 * La carrera se provoca a voluntad, sin esperas ni azar: justo cuando el
 * primero ya comprobó que la sala estaba libre y va a escribir, entra el
 * segundo por la otra conexión.
 */

beforeEach(function (): void {
    Event::fake([SalaAsignada::class]);
});

/** Montaje de una práctica aprobada el 10 de abril, todavía sin sala. */
function montajeSinSala(string $inicio, string $fin): Preparacion
{
    $solicitud = Solicitud::factory()->aprobada()->create([
        'fecha' => '2026-04-10',
        'hora_inicio' => $inicio,
        'hora_fin' => $fin,
    ]);

    return Preparacion::factory()->create(['solicitud_id' => $solicitud->id]);
}

/** Lo que hace el segundo administrativo, desde su propia sesión de base de datos. */
function asignarDesdeOtraSesion(int $preparacionId, int $salaId): void
{
    enOtraSesion(fn () => app(PreparacionService::class)->asignarSala(Preparacion::findOrFail($preparacionId), Sala::findOrFail($salaId)));
}

it('dos administrativos que asignan a la vez la misma sala a franjas que se cruzan no la reservan dos veces', function (): void {
    $sala = Sala::factory()->create();
    $primero = montajeSinSala('07:00:00', '09:00:00');
    $segundo = montajeSinSala('08:00:00', '10:00:00');

    $seInterpuso = false;

    Preparacion::updating(function (Preparacion $montaje) use (&$seInterpuso, $primero, $segundo, $sala): void {
        if ($seInterpuso || $montaje->id !== $primero->id) {
            return;
        }

        $seInterpuso = true;

        try {
            asignarDesdeOtraSesion($segundo->id, $sala->id);
        } catch (SalaOcupada|QueryException) {
            // Si la sala está protegida, el segundo no la consigue: la ve
            // ocupada o se cansa de esperar el candado del primero.
        }
    });

    app(PreparacionService::class)->asignarSala($primero, $sala);

    // El segundo vuelve a intentarlo cuando el primero ya terminó.
    try {
        asignarDesdeOtraSesion($segundo->id, $sala->id);
    } catch (SalaOcupada) {
        // Lo esperado: la sala ya es del primero.
    }

    expect($seInterpuso)->toBeTrue()
        ->and(Preparacion::query()->where('sala_id', $sala->id)->count())->toBe(1)
        ->and($primero->fresh()->sala_id)->toBe($sala->id)
        ->and($segundo->fresh()->sala_id)->toBeNull();
});
