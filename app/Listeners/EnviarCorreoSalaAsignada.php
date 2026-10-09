<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\SalaAsignada;
use App\Mail\SalaAsignadaMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

/**
 * Avisa al docente en qué sala será su sesión (RF36), también si cambia. Va
 * en cola para no frenar al administrativo, que asigna desde el celular.
 */
final class EnviarCorreoSalaAsignada implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(SalaAsignada $evento): void
    {
        $preparacion = $evento->preparacion->loadMissing(['sala', 'solicitud.docente', 'solicitud.casoClinico', 'solicitud.materia']);

        Mail::to($preparacion->solicitud->docente->email)
            ->send(new SalaAsignadaMail($preparacion, $evento->esCambio));
    }
}
