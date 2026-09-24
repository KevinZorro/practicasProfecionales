<?php

declare(strict_types=1);

use App\Http\Middleware\EstablecerRolActivo;
use App\Http\Middleware\VerificarUsuarioActivo;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;

/**
 * Las acciones de un componente ya abierto no pasan por las rutas del panel:
 * van a /livewire/update, y Livewire solo vuelve a aplicar ahí los
 * middleware de su lista de persistentes. Un middleware de autorización que
 * no esté en esa lista protege la carga de la página y no protege los
 * botones. Así se descubrió que VerificarUsuarioActivo dejaba actuar a un
 * usuario desactivado con una pantalla abierta, y que EstablecerRolActivo
 * dejaba que los botones se evaluaran con todos los roles del usuario.
 *
 * Este test recorre las rutas del panel y exige que cada middleware que
 * decide quién entra o qué puede hacer esté también en la lista persistente.
 */

/**
 * Los middleware que deciden acceso en las rutas de los dos paneles —el de
 * Livewire y el del ADMIN en Filament—: los propios del proyecto, los de
 * autenticación de Laravel y los que heredan de ellos, como el Authenticate
 * de Filament, que además pregunta canAccessPanel(). Los alias se resuelven
 * a su clase; los grupos (como "web") no deciden acceso y se dejan fuera.
 *
 * @return list<class-string>
 */
function middlewareDeAutorizacionDelPanel(): array
{
    $alias = app('router')->getMiddleware();

    return collect(Route::getRoutes()->getRoutes())
        ->filter(static fn ($ruta): bool => str_starts_with((string) $ruta->getName(), 'panel.')
            || str_starts_with((string) $ruta->getName(), 'filament.admin.'))
        ->flatMap(static fn ($ruta): array => $ruta->gatherMiddleware())
        ->filter(static fn ($middleware): bool => is_string($middleware))
        ->map(static fn (string $middleware): string => $alias[explode(':', $middleware)[0]] ?? $middleware)
        ->filter(static fn (string $clase): bool => str_starts_with($clase, 'App\\Http\\Middleware\\')
            || str_starts_with($clase, 'Illuminate\\Auth\\Middleware\\')
            || is_subclass_of($clase, Authenticate::class))
        ->unique()
        ->values()
        ->all();
}

it('protege también las acciones de Livewire con cada middleware de autorización del panel', function (): void {
    $persistentes = app(PersistentMiddleware::class)->getPersistentMiddleware();

    $sinPersistir = array_values(array_filter(
        middlewareDeAutorizacionDelPanel(),
        static fn (string $clase): bool => ! in_array($clase, $persistentes, true),
    ));

    expect($sinPersistir)->toBe([], sprintf(
        'Estos middleware protegen la página pero no las acciones de Livewire: %s. Regístralos con Livewire::addPersistentMiddleware() en AppServiceProvider y pruébalos con una petición real a /livewire/update.',
        implode(', ', $sinPersistir),
    ));
});

it('encuentra los middleware del panel que tiene que vigilar', function (): void {
    expect(middlewareDeAutorizacionDelPanel())
        ->toContain(Authenticate::class)
        ->toContain(VerificarUsuarioActivo::class)
        ->toContain(EstablecerRolActivo::class)
        ->toContain(FilamentAuthenticate::class);
});
