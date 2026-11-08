<?php

declare(strict_types=1);

use App\Support\EsperaEntreReintentos;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;

/*
 * B3.2 de la auditoría: queue:work reintenta 3 veces. Sin espera entre
 * intentos, un corte de Gmail de un par de minutos gasta los tres en
 * segundos y el correo queda en failed_jobs sin que nadie lo vea. Y si el
 * trabajo puede volver a la cola mientras el primer worker sigue con él, el
 * mismo correo sale dos veces.
 */

/**
 * Los listeners que van a la cola y todos los correos.
 *
 * @return list<class-string>
 */
function loQueVaALaCola(): array
{
    $clases = [];

    foreach (['Listeners', 'Mail'] as $carpeta) {
        // El dataset se arma antes de que exista la aplicación: sin app_path().
        foreach (glob(dirname(__DIR__, 2)."/app/{$carpeta}/*.php") ?: [] as $archivo) {
            $clase = "App\\{$carpeta}\\".basename($archivo, '.php');

            if (is_subclass_of($clase, ShouldQueue::class) || is_subclass_of($clase, Mailable::class)) {
                $clases[] = $clase;
            }
        }
    }

    return $clases;
}

it('espera entre reintentos en cada listener de la cola y en cada correo', function (string $clase): void {
    expect(class_uses_recursive($clase))->toContain(EsperaEntreReintentos::class)
        ->and((new ReflectionClass($clase))->getDefaultProperties()['backoff'] ?? null)->toBe([60, 300]);
})->with(fn (): array => loQueVaALaCola());

it('no devuelve a la cola un trabajo que el worker todavía no termina', function (): void {
    // Laravel exige retry_after mayor que el --timeout del worker: si no, con
    // un trabajo lento otro worker lo toma y el correo sale dos veces.
    preg_match('/"--timeout=(\d+)"/', (string) file_get_contents(base_path('docker-compose.produccion.yml')), $timeout);

    expect($timeout)->toHaveKey(1)
        ->and((int) config('queue.connections.database.retry_after'))->toBeGreaterThan((int) $timeout[1]);
});
