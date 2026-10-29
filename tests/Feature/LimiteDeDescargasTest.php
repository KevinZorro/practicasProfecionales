<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Support\Facades\RateLimiter;

/*
 * S-02 del informe de seguridad: las descargas pesadas tienen un tope por
 * persona, no por IP. La respuesta de cada una da igual aquí (sin rango de
 * fechas el PDF responde 422): el limitador cuenta peticiones, no éxitos.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    RateLimiter::clear('descargas');
    $this->ruta = route('panel.reportes.pdf', ['reporte' => 'uso_de_escenarios']);
});

it('frena a quien pide más de treinta descargas por minuto', function (): void {
    $coordinadora = User::factory()->coordinador()->create();

    for ($i = 0; $i < 30; $i++) {
        $this->actingAs($coordinadora)->get($this->ruta)->assertStatus(422);
    }

    $this->actingAs($coordinadora)->get($this->ruta)->assertStatus(429);
});

it('cuenta por persona: una IP compartida no deja sin descargas a otro usuario', function (): void {
    $desbocada = User::factory()->coordinador()->create();
    $otra = User::factory()->coordinador()->create();

    for ($i = 0; $i < 31; $i++) {
        $this->actingAs($desbocada)->get($this->ruta);
    }

    $this->actingAs($desbocada)->get($this->ruta)->assertStatus(429);
    $this->actingAs($otra)->get($this->ruta)->assertStatus(422);
});

it('pone el tope en todas las rutas de descarga pesada', function (): void {
    $descargas = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($ruta): bool => str_starts_with((string) $ruta->getName(), 'panel.')
            && preg_match('/(\.excel|\.pdf|\.firmado|\.plantilla|\.version)$/', (string) $ruta->getName()) === 1);

    expect($descargas)->not->toBeEmpty();

    $sinTope = $descargas
        ->reject(fn ($ruta): bool => in_array('throttle:descargas', $ruta->gatherMiddleware(), true))
        ->map(fn ($ruta): string => (string) $ruta->getName())
        ->values()
        ->all();

    expect($sinTope)->toBe([]);
});
