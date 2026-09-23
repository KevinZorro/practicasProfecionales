<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Rol;
use App\Models\User;
use App\Support\MenuDelPanel;
use App\Support\RolActivo;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija el rol activo de la petición (RF21).
 *
 * Corre antes que cualquier Policy, de modo que todo lo que venga después
 * —navegación, controladores, vistas— ve al usuario con un solo rol: el
 * elegido en el selector.
 *
 * Si el usuario no tiene ningún rol asignado, no entra: es una cuenta a
 * medio configurar, no un caso de uso.
 *
 * Si la sesión trae un rol que el usuario no tiene asignado, se descarta y
 * se usa el que sí le corresponde. No aborta: un rol retirado por el
 * administrador dejaría al usuario fuera del panel sin explicación, y
 * caerse al rol legítimo es igual de seguro —de la manipulación no sale
 * ningún permiso extra— y mucho menos desconcertante.
 *
 * La excepción son las acciones de Livewire. Corre también en ellas
 * (middleware persistente, AppServiceProvider), porque van a
 * /livewire/update y no pasan por las rutas del panel. Si el rol con el que
 * se abrió la pantalla ya no vale, la acción se corta en vez de caer a otro
 * rol: el botón se pulsó con un rol que ya no se tiene. Al recargar, la
 * navegación normal lo lleva a su rol legítimo.
 */
final class EstablecerRolActivo
{
    public function __construct(
        private readonly RolActivo $rolActivo,
        private readonly MenuDelPanel $menu,
    ) {}

    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $usuario = $peticion->user();

        if ($usuario === null) {
            return $siguiente($peticion);
        }

        $rol = $this->rolActivo->actual($usuario);

        // Sin ningún rol no hay panel que enseñar: la sincronización
        // institucional puede crear la cuenta antes de que el ADMIN le
        // asigne el suyo. Mejor decirlo que reventar al pintar la cabecera.
        if ($rol === null) {
            $this->rolActivo->olvidar();

            abort(403, 'Tu cuenta todavía no tiene ningún rol asignado en el laboratorio.');
        }

        if (Livewire::isLivewireRequest() && $this->rolActivo->seRetiroElDeLaSesion($usuario)) {
            abort(403, 'El rol con el que abriste esta pantalla ya no está vigente. Recarga la página.');
        }

        // Se guarda aunque sea el de siempre: es lo que permite reconocer,
        // en la acción siguiente, que la pantalla se abrió con un rol que
        // después se retiró.
        $this->rolActivo->establecer($usuario, $rol);
        $this->rolActivo->aplicar($usuario, $rol);
        $this->compartirConLasVistas($usuario, $rol);

        return $siguiente($peticion);
    }

    /**
     * Fecha de fin de cada rol disponible, para marcar los temporales en el
     * selector. Null en los permanentes.
     *
     * @return array<string, ?CarbonImmutable>
     */
    private function vigenciaDeCadaRol(User $usuario): array
    {
        $vigencias = [];

        foreach ($this->rolActivo->disponibles($usuario) as $disponible) {
            $vigencias[$disponible->value] = $this->rolActivo->vigenciaDe($usuario, $disponible);
        }

        return $vigencias;
    }

    /**
     * La navegación se calcula aquí, ya con el rol aplicado, para que la
     * vista solo tenga que recorrer una lista y no consulte permisos por su
     * cuenta.
     */
    private function compartirConLasVistas(User $usuario, Rol $rol): void
    {
        View::share('rolActivo', $rol);
        View::share('rolesDisponibles', $this->rolActivo->disponibles($usuario));
        View::share('seccionesDelMenu', $this->menu->visiblesPara($usuario));

        // RF63-RF64: quien está usando un rol prestado tiene que saber que
        // lo es y hasta cuándo, sin ir a buscarlo a otra pantalla.
        View::share('vigenciaDelRolActivo', $this->rolActivo->vigenciaDe($usuario, $rol));
        View::share('vigenciaDeLosRoles', $this->vigenciaDeCadaRol($usuario));
    }
}
