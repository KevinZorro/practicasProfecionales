<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;

/**
 * Laravel resuelve las Policies por convención de nombre: App\Models\X se
 * empareja con App\Policies\XPolicy. Si el nombre no coincide, no hay
 * error: Gate::getPolicyFor() devuelve null y el Gate deniega en silencio,
 * lo que parece un problema de permisos y cuesta encontrar.
 *
 * Ya ha pasado dos veces en este proyecto (EvaluacionEstudiantePolicy e
 * ItemInventarioPolicy). Este test lo vigila para que no haya una tercera.
 */

// La ruta va relativa a este archivo, no por app_path(): los dataset se
// resuelven antes de que la aplicación esté arrancada.
dataset('policies del proyecto', fn (): array => collect(glob(__DIR__.'/../../app/Policies/*.php'))
    ->map(static fn (string $ruta): string => basename($ruta, '.php'))
    ->mapWithKeys(static fn (string $clase): array => [
        $clase => [
            "App\\Policies\\{$clase}",
            'App\\Models\\'.str_replace('Policy', '', $clase),
        ],
    ])
    ->all());

it('empareja cada Policy con un modelo que existe', function (string $policy, string $modelo): void {
    expect(class_exists($modelo))->toBeTrue(
        "{$policy} no tiene modelo: se esperaba {$modelo}. Laravel no la descubrirá y el Gate denegará en silencio.",
    );
})->with('policies del proyecto');

it('deja que Laravel descubra cada Policy desde su modelo', function (string $policy, string $modelo): void {
    $descubierta = Gate::getPolicyFor($modelo);

    expect($descubierta)->not->toBeNull("Laravel no descubre {$policy} desde {$modelo}.")
        ->and($descubierta::class)->toBe($policy);
})->with('policies del proyecto');

it('vigila todas las Policies del proyecto', function (): void {
    $enDisco = count(glob(__DIR__.'/../../app/Policies/*.php'));

    expect($enDisco)->toBeGreaterThan(0);
});
