<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Rol;
use App\Events\SolicitudAprobada;
use App\Events\SolicitudRechazada;
use App\Listeners\EnviarCorreoResultadoSolicitud;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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

        $this->registrarPermisoDeReportes();
    }

    /**
     * Los reportes agregados (RF54-RF56) no tienen modelo propio: son
     * consultas sobre datos de otros módulos. Por eso esto es un Gate y no
     * una Policy — Laravel descubre las Policies emparejándolas con un
     * modelo, y aquí no hay ninguno que emparejar.
     *
     * Según el §6.1 del documento de arquitectura los genera el ADMIN y el
     * coordinador. El administrativo queda fuera, igual que en la
     * verificación del consentimiento. Docentes y estudiantes tampoco
     * entran: su historial propio lo cubren el RF35, el RF49 y el RF50, que
     * son otra cosa.
     */
    private function registrarPermisoDeReportes(): void
    {
        Gate::define('generarReportes', static fn (User $usuario): bool => $usuario->hasAnyRole([
            Rol::Admin->value,
            Rol::Coordinador->value,
        ]));
    }
}
