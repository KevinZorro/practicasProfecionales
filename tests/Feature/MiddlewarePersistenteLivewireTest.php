<?php

declare(strict_types=1);

use App\Http\Middleware\EstablecerRolActivo;
use App\Http\Middleware\VerificarUsuarioActivo;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;

/**
 * Las acciones de un componente ya abierto no pasan por las rutas del panel:
 * van a /livewire/update, y Livewire solo vuelve a aplicar ahí los
 * middleware de su lista de persistentes. Un middleware de autorización que
 * no esté en esa lista protege la carga de la página y no protege los
 * botones. Así se descubrió que VerificarUsuarioActivo dejaba actuar a un
 * usuario desactivado con una pantalla abierta.
 *
 * Este test recorre las rutas del panel y exige que cada middleware que
 * decide quién entra o qué puede hacer esté también en la lista persistente.
 */

/**
 * Pendientes de decisión, no olvidos. Cada uno con su porqué; cuando se
 * resuelva, se quita de aquí.
 *
 * @var array<class-string, string>
 */
const MIDDLEWARE_PENDIENTES_DE_DECISION = [
    // Recorta los roles al elegido en el selector (RF21). Sin persistencia, las
    // acciones de Livewire se evalúan con todos los roles del usuario. No es
    // escalada de privilegios, pero sí incoherente: pendiente de que el
    // cliente decida si se corrige.
    EstablecerRolActivo::class => 'pendiente de decisión (RF21)',
];

/**
 * Los middleware de las rutas del panel que deciden acceso: los propios del
 * proyecto y los de autenticación de Laravel. Los alias se resuelven a su
 * clase; los grupos (como "web") no deciden acceso y se dejan fuera.
 *
 * @return list<class-string>
 */
function middlewareDeAutorizacionDelPanel(): array
{
    $alias = app('router')->getMiddleware();

    return collect(Route::getRoutes()->getRoutes())
        ->filter(static fn ($ruta): bool => str_starts_with((string) $ruta->getName(), 'panel.'))
        ->flatMap(static fn ($ruta): array => $ruta->gatherMiddleware())
        ->map(static fn (string $middleware): string => $alias[explode(':', $middleware)[0]] ?? $middleware)
        ->filter(static fn (string $clase): bool => str_starts_with($clase, 'App\\Http\\Middleware\\')
            || str_starts_with($clase, 'Illuminate\\Auth\\Middleware\\'))
        ->unique()
        ->values()
        ->all();
}

it('protege también las acciones de Livewire con cada middleware de autorización del panel', function (): void {
    $persistentes = app(PersistentMiddleware::class)->getPersistentMiddleware();

    $sinPersistir = array_values(array_filter(
        middlewareDeAutorizacionDelPanel(),
        static fn (string $clase): bool => ! in_array($clase, $persistentes, true)
            && ! array_key_exists($clase, MIDDLEWARE_PENDIENTES_DE_DECISION),
    ));

    expect($sinPersistir)->toBe([], sprintf(
        'Estos middleware protegen la página pero no las acciones de Livewire: %s. Regístralos con Livewire::addPersistentMiddleware() en AppServiceProvider y pruébalos con una petición real a /livewire/update.',
        implode(', ', $sinPersistir),
    ));
});

it('no guarda excepciones que ya no hacen falta', function (): void {
    // Si un pendiente se resuelve y se registra como persistente, su
    // excepción tiene que salir de la lista: si no, la lista miente.
    $persistentes = app(PersistentMiddleware::class)->getPersistentMiddleware();

    foreach (array_keys(MIDDLEWARE_PENDIENTES_DE_DECISION) as $clase) {
        expect(in_array($clase, $persistentes, true))->toBeFalse("{$clase} ya es persistente: quítalo de las excepciones.");
    }
});

it('encuentra los middleware del panel que tiene que vigilar', function (): void {
    expect(middlewareDeAutorizacionDelPanel())
        ->toContain(Authenticate::class)
        ->toContain(VerificarUsuarioActivo::class)
        ->toContain(EstablecerRolActivo::class);
});
