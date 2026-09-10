<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\SolicitudAprobada;
use App\Events\SolicitudRechazada;
use App\Mail\SolicitudAprobadaMail;
use App\Mail\SolicitudRechazadaMail;
use App\Models\Solicitud;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

/**
 * Avisa al docente del resultado de su solicitud (RF33). Va en cola para no
 * bloquear la respuesta HTTP de quien aprueba o rechaza.
 */
final class EnviarCorreoResultadoSolicitud implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(SolicitudAprobada|SolicitudRechazada $evento): void
    {
        $solicitud = $evento->solicitud->loadMissing(['docente', 'materia', 'casoClinico']);

        Mail::to($solicitud->docente->email)->send($this->correoPara($evento, $solicitud));
    }

    private function correoPara(
        SolicitudAprobada|SolicitudRechazada $evento,
        Solicitud $solicitud,
    ): SolicitudAprobadaMail|SolicitudRechazadaMail {
        return $evento instanceof SolicitudAprobada
            ? new SolicitudAprobadaMail($solicitud)
            : new SolicitudRechazadaMail($solicitud);
    }
}
