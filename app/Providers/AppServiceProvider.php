<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Rol;
use App\Events\SolicitudAprobada;
use App\Events\SolicitudRechazada;
use App\Http\Middleware\EstablecerRolActivo;
use App\Http\Middleware\VerificarUsuarioActivo;
use App\Listeners\EnviarCorreoResultadoSolicitud;
use App\Models\User;
use App\Support\RolActivo;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Una instancia por petición: cachea los roles asignados del usuario
        // para no repetir la consulta en cada comprobación.
        $this->app->scoped(RolActivo::class);
    }

    public function boot(): void
    {
        // Registro explícito en lugar de descubrimiento automático: así el
        // enlace entre evento y listener se lee de un vistazo.
        Event::listen(SolicitudAprobada::class, EnviarCorreoResultadoSolicitud::class);
        Event::listen(SolicitudRechazada::class, EnviarCorreoResultadoSolicitud::class);

        $this->registrarPermisoDeReportes();
        $this->registrarAccesoAlPanelDelAdmin();

        // Las acciones de un componente ya abierto van a /livewire/update,
        // fuera del grupo de rutas del panel. Livewire solo vuelve a aplicar
        // ahí los middleware de esta lista, así que sin ellos quien se
        // desactivara con una pantalla abierta seguiría pudiendo actuar
        // (regla 8), y los botones se evaluarían con todos sus roles en vez
        // de con el activo (RF21).
        Livewire::addPersistentMiddleware([
            VerificarUsuarioActivo::class,
            EstablecerRolActivo::class,
        ]);
    }

    /**
     * Las pantallas de Filament (estructura académica y contenido público)
     * son solo del ADMIN, sin herencia: el coordinador no administra la
     * plataforma. Qué puede hacer dentro de cada pantalla lo sigue diciendo
     * la Policy de su modelo; esto solo abre la puerta del panel.
     */
    private function registrarAccesoAlPanelDelAdmin(): void
    {
        Gate::define('accederAlPanelDelAdmin', static fn (User $usuario): bool => $usuario->hasRole(Rol::Admin->value));
    }

    /**
     * Los reportes agregados (RF54-RF56) no tienen modelo propio: son
     * consultas sobre datos de otros módulos. Por eso esto es un Gate y no
     * una Policy — Laravel descubre las Policies emparejándolas con un
     * modelo, y aquí no hay ninguno que emparejar.
     *
     * Según el §6.1 del documento de arquitectura los genera el ADMIN y el
     * coordinador. El administrativo queda fuera: es de las pocas funciones
     * donde no acompaña al coordinador. Docentes y estudiantes tampoco
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
