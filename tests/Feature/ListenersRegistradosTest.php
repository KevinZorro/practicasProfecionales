<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * El descubrimiento automático de listeners está apagado (bootstrap/app.php):
 * con él encendido y el registro a mano de AppServiceProvider, cada listener
 * quedaba dos veces y el docente recibía dos correos por aprobación.
 *
 * El precio es que un listener nuevo que nadie registre no se ejecuta nunca,
 * y no da ningún error: el evento se despacha y no pasa nada. Es el mismo
 * tipo de fallo silencioso que las Policies mal nombradas, y se vigila igual:
 * se recorre app/Listeners y cada clase tiene que estar registrada para cada
 * evento que declara en su handle().
 */

// La ruta va relativa a este archivo, no por app_path(): los dataset se
// resuelven antes de que la aplicación esté arrancada.
dataset('listeners del proyecto', fn (): array => collect(glob(__DIR__.'/../../app/Listeners/*.php'))
    ->map(static fn (string $ruta): string => 'App\\Listeners\\'.basename($ruta, '.php'))
    ->mapWithKeys(static fn (string $clase): array => [class_basename($clase) => [$clase]])
    ->all());

/**
 * Los eventos que el listener dice escuchar, sacados del tipo del parámetro
 * de handle(). Admite uniones: SolicitudAprobada|SolicitudRechazada.
 *
 * @return list<string>
 */
function eventosQueEscucha(string $listener): array
{
    $parametros = (new ReflectionMethod($listener, 'handle'))->getParameters();
    $tipo = $parametros[0]?->getType();

    $tipos = $tipo instanceof ReflectionUnionType ? $tipo->getTypes() : [$tipo];

    return array_values(array_map(
        static fn (ReflectionNamedType $t): string => $t->getName(),
        array_filter($tipos, static fn (mixed $t): bool => $t instanceof ReflectionNamedType && ! $t->isBuiltin()),
    ));
}

/**
 * Los listeners registrados para un evento, por nombre de clase. El
 * descubrimiento automático los registra como "Clase@handle" y el registro a
 * mano como "Clase": sin normalizar, un listener registrado por las dos vías
 * parecería estarlo una sola vez.
 *
 * @return list<string>
 */
function listenersRegistradosPara(string $evento): array
{
    return array_values(array_map(
        static fn (mixed $listener): string => is_string($listener) ? Str::before($listener, '@') : get_debug_type($listener),
        Event::getRawListeners()[$evento] ?? [],
    ));
}

it('declara en handle() qué eventos escucha', function (string $listener): void {
    expect(method_exists($listener, 'handle'))->toBeTrue("{$listener} no tiene handle().")
        ->and(eventosQueEscucha($listener))->not->toBeEmpty(
            "{$listener} no tipa el evento de handle(): no se puede comprobar que esté registrado.",
        );
})->with('listeners del proyecto');

it('registra cada listener para cada evento que escucha', function (string $listener): void {
    foreach (eventosQueEscucha($listener) as $evento) {
        expect(in_array($listener, listenersRegistradosPara($evento), true))->toBeTrue(
            "{$listener} escucha {$evento} pero no está registrado: con el descubrimiento apagado no se ejecutaría nunca. Regístralo en AppServiceProvider.",
        );
    }
})->with('listeners del proyecto');

it('registra cada listener una sola vez por evento', function (string $listener): void {
    foreach (eventosQueEscucha($listener) as $evento) {
        $veces = count(array_keys(listenersRegistradosPara($evento), $listener, true));

        expect($veces)->toBe(1, "{$listener} está registrado {$veces} veces para {$evento}: cada evento lo ejecutaría {$veces} veces.");
    }
})->with('listeners del proyecto');

it('vigila todos los listeners del proyecto', function (): void {
    expect(count(glob(__DIR__.'/../../app/Listeners/*.php')))->toBeGreaterThan(0);
});
