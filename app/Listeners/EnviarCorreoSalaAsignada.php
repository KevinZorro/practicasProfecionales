<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\SalaAsignada;
use App\Mail\SalaAsignadaMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

/**
 * Avisa al docente que dicta en qué sala será su sesión (RF36), también si cambia. Va
 * en cola para no frenar al administrativo, que asigna desde el celular.
 */
final class EnviarCorreoSalaAsignada implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(SalaAsignada $evento): void
    {
        $preparacion = $evento->preparacion->loadMissing(['sala', 'solicitud.docente', 'solicitud.docenteQueDicta', 'solicitud.casoClinico', 'solicitud.materia']);

        // Quien dicta, que con una sustitución (RF73) no es el titular.
        $destinatario = $preparacion->solicitud->docenteQueDicta ?? $preparacion->solicitud->docente;

        Mail::to($destinatario->email)
            ->send(new SalaAsignadaMail($preparacion, $evento->esCambio));
    }
}
