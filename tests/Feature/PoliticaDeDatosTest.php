<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolSeeder;

/*
 * QA-11 (Ley 1581 de 2012, Decreto 1377 de 2013): la plataforma trata datos
 * personales y tiene que enlazar la política de tratamiento de la
 * universidad. La URL la fija POLITICA_DE_DATOS_URL; vacía, no hay enlace.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
});

it('enlaza la política de datos en el pie de la portada y en el panel', function (): void {
    config(['laboratorio.politica_de_datos_url' => 'https://www.ufps.edu.co/politica-de-datos']);

    $portada = $this->get('/')->assertOk()->getContent();
    $panel = $this->actingAs(User::factory()->docente()->create())->get(route('panel.inicio'))->assertOk()->getContent();

    foreach ([$portada, $panel] as $html) {
        expect($html)->toContain('href="https://www.ufps.edu.co/politica-de-datos"')
            ->toContain('Política de tratamiento de datos personales')
            ->toContain('rel="noopener noreferrer"');
    }
});

it('oculta el enlace mientras la universidad no entregue la URL', function (?string $url): void {
    config(['laboratorio.politica_de_datos_url' => $url]);

    $this->get('/')->assertOk()->assertDontSee('Política de tratamiento de datos personales');
    $this->actingAs(User::factory()->docente()->create())->get(route('panel.inicio'))->assertOk()
        ->assertDontSee('Política de tratamiento de datos personales');
})->with([null, '', '   ']);

it('no acepta como enlace algo que no sea http o https', function (): void {
    config(['laboratorio.politica_de_datos_url' => 'javascript:alert(1)']);

    $this->get('/')->assertOk()->assertDontSee('javascript:alert(1)', false);
});

it('trae la variable en los ejemplos de configuración, vacía', function (): void {
    foreach (['.env.example', '.env.produccion.example'] as $archivo) {
        expect((string) file_get_contents(base_path($archivo)))->toMatch('/^POLITICA_DE_DATOS_URL=$/m');
    }
});
