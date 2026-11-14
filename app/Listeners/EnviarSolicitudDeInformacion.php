<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\ClaveConfiguracionLanding;
use App\Events\SolicitudDeInformacionRecibida;
use App\Mail\SolicitudDeInformacionMail;
use App\Services\ConfiguracionLandingService;
use App\Support\EsperaEntreReintentos;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Manda al correo de contacto del laboratorio la solicitud de información
 * de un taller (RF09, D8) y marca cuándo salió. En cola: el visitante no
 * espera a Gmail.
 */
final class EnviarSolicitudDeInformacion implements ShouldQueue
{
    use EsperaEntreReintentos;
    use InteractsWithQueue;

    public function __construct(private readonly ConfiguracionLandingService $configuracion) {}

    public function handle(SolicitudDeInformacionRecibida $evento): void
    {
        $solicitud = $evento->solicitud->loadMissing('taller');

        // Ya salió en un intento anterior: un reintento no la repite.
        if ($solicitud->enviado_at !== null) {
            return;
        }

        $correo = $this->configuracion->valores()[ClaveConfiguracionLanding::ContactoEmail->value];

        // Se quitó el correo de contacto entre el envío y la cola: queda
        // guardada sin enviar, y el registro lo dice.
        if ($correo === null) {
            Log::warning('Solicitud de información sin correo de contacto adonde enviarla.', ['solicitud' => $solicitud->id]);

            return;
        }

        Mail::to($correo)->send(new SolicitudDeInformacionMail($solicitud));
        $solicitud->forceFill(['enviado_at' => now()])->save();
    }
}
