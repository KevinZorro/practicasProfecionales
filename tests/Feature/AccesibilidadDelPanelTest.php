<?php

declare(strict_types=1);

use App\Models\Solicitud;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Support\Facades\Blade;

/*
 * QA-12 (WCAG 2.2 AA): lo que axe-core y la prueba con teclado encontraron.
 * La auditoría completa con axe corre en un navegador; aquí se vigila lo que
 * se puede comprobar en el HTML para que no vuelva.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->usuario = User::factory()->coordinador()->create();
});

it('abre el menú del celular con un botón con nombre, estado y foco visible', function (): void {
    $html = $this->actingAs($this->usuario)->get(route('panel.inicio'))->assertOk()->getContent();

    // El botón que abre el menú: nombre, estado y a qué región controla.
    expect($html)->toMatch('/<button[^>]*aria-controls="menu-del-panel"[^>]*>/')
        ->toContain('aria-expanded')
        ->toMatch('/<button[^>]*aria-controls="menu-del-panel"[^>]*focus-visible:outline/s')
        ->toContain('id="menu-del-panel"');

    // La casilla que guarda el estado para el CSS ya no es un control para
    // el teclado ni para el lector de pantalla.
    expect($html)->toMatch('/<input[^>]*id="alternar-menu"[^>]*aria-hidden="true"[^>]*tabindex="-1"/s')
        ->not->toContain('<label for="alternar-menu"');
});

it('el botón del menú tiene texto accesible para abrir y para cerrar', function (): void {
    $html = $this->actingAs($this->usuario)->get(route('panel.inicio'))->getContent();

    expect($html)->toContain('Abrir el menú')->toContain('Cerrar el menú');
});

it('la fecha de cada solicitud en las listas tiene contraste suficiente', function (): void {
    Solicitud::factory()->aprobada()->create();

    $html = $this->actingAs($this->usuario)->get(route('panel.solicitudes'))->assertOk()->getContent();

    // text-gray-500 sobre el gris del hover da 4,34:1; el mínimo es 4,5:1.
    expect($html)->toMatch('/class="block text-\[12px\] font-semibold text-gray-600"/')
        ->not->toMatch('/class="block text-\[12px\] font-semibold text-gray-500"/');
});

it('la traducción de la paginación usa los caracteres y no la entidad HTML', function (): void {
    // La vista de Livewire la escapa en aria-label: «&laquo;» se leía tal cual.
    expect(__('pagination.previous'))->toBe('« Anterior')
        ->and(__('pagination.next'))->toBe('Siguiente »');
});

it('la tabla con desplazamiento horizontal se puede recorrer con el teclado', function (): void {
    $html = Blade::render('<x-tabla etiqueta="Resultados del reporte"><tr><td>x</td></tr></x-tabla>');

    expect($html)->toContain('tabindex="0"')
        ->toContain('role="region"')
        ->toContain('aria-label="Resultados del reporte"');
});

it('la tabla sin etiqueta no inventa una región sin nombre', function (): void {
    $html = Blade::render('<x-tabla><tr><td>x</td></tr></x-tabla>');

    expect($html)->not->toContain('role="region"');
});

it('el calendario pone texto a los botones de anterior y siguiente', function (): void {
    $js = (string) file_get_contents(base_path('resources/js/calendario.js'));

    // Sin esto FullCalendar pinta un ícono con role="img" y sin texto.
    expect($js)->toContain('buttonText')
        ->toContain("prev: 'Anterior'")
        ->toContain("next: 'Siguiente'");
});

it('las casillas miden al menos 24 por 24 píxeles', function (): void {
    $css = (string) file_get_contents(base_path('resources/css/app.css'));

    expect($css)->toMatch('/\.casilla\s*\{[^}]*size-6/');
});
