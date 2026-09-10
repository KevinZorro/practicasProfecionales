<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\SolicitudAprobada;
use App\Events\SolicitudRechazada;
use App\Listeners\EnviarCorreoResultadoSolicitud;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Registro explícito en lugar de descubrimiento automático: así el
        // enlace entre evento y listener se lee de un vistazo.
        Event::listen(SolicitudAprobada::class, EnviarCorreoResultadoSolicitud::class);
        Event::listen(SolicitudRechazada::class, EnviarCorreoResultadoSolicitud::class);
    }
}
