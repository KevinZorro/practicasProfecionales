<?php

declare(strict_types=1);

use App\Enums\Rol;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
 * Las páginas de error dicen qué pasó en palabras de la persona y dan una
 * salida. Las de Laravel decían «Prohibido» o «No encontrado» y nada más.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
});

/** Una ruta de prueba que falla como se le pida, dentro o fuera del panel. */
function rutaQueFalla(string $uri, Closure $falla): string
{
    Route::middleware('web')->get($uri, $falla);

    return url($uri);
}

it('explica el 403 y lleva a «Hoy»', function (): void {
    $docente = User::factory()->docente()->create();

    $this->actingAs($docente)->get(route('panel.reportes'))
        ->assertForbidden()
        ->assertSee('No puedes ver esto')
        ->assertSee('No tienes permiso para hacer esto.')
        ->assertSee('href="'.url('/panel').'"', false)
        ->assertSee('Ir a «Hoy»')
        ->assertDontSee('Forbidden');
});

it('le dice a quien tiene otro rol que puede cambiarlo', function (): void {
    // Con el rol equivocado puesto, el 403 parecía un error del sistema.
    $coordinadoraDocente = User::factory()->create();
    $coordinadoraDocente->assignRole(Rol::Coordinador->value, Rol::Docente->value);
    $this->actingAs($coordinadoraDocente->fresh())->post(route('panel.rol-activo'), ['rol' => Rol::Docente->value]);

    $this->get(route('panel.reportes'))
        ->assertForbidden()
        ->assertSee('cámbialo en el panel, arriba a la derecha');

    $this->actingAs(User::factory()->docente()->create())->get(route('panel.reportes'))
        ->assertForbidden()
        ->assertDontSee('cámbialo en el panel');
});

it('dice el porqué del 403 cuando se sabe', function (): void {
    $sinRol = User::factory()->create();

    $this->actingAs($sinRol)->get(route('panel.inicio'))
        ->assertForbidden()
        ->assertSee('Tu cuenta todavía no tiene ningún rol asignado en el laboratorio.');
});

it('explica el 404: dentro del panel lleva a «Hoy» y fuera, a la portada', function (): void {
    $this->actingAs(User::factory()->docente()->create())->get('/panel/no-existe')
        ->assertNotFound()
        ->assertSee('No encontramos esta página')
        ->assertSee('Ir a «Hoy»');

    $this->get('/pagina-que-no-existe')
        ->assertNotFound()
        ->assertSee('No encontramos esta página')
        ->assertSee('Ir a la portada')
        ->assertDontSee('Ir a «Hoy»');
});

it('explica la sesión caducada y vuelve a la página de la que venía', function (): void {
    $url = rutaQueFalla('panel/prueba-419', fn () => abort(419));

    $this->get($url, ['referer' => url('/panel/solicitudes')])
        ->assertStatus(419)
        ->assertSee('Tu sesión caducó')
        ->assertSee('href="'.url('/panel/solicitudes').'"', false);

    // Un referer de otro sitio no se ofrece como vuelta.
    $this->get($url, ['referer' => 'https://otro-sitio.example/trampa'])
        ->assertDontSee('otro-sitio.example')
        ->assertSee('href="'.url('/panel').'"', false);
});

it('no enseña el detalle de un fallo del servidor', function (): void {
    config(['app.debug' => false]);
    $url = rutaQueFalla('panel/prueba-500', fn () => throw new RuntimeException('SQLSTATE detalle interno'));

    $this->get($url)
        ->assertStatus(500)
        ->assertSee('Algo falló de nuestro lado')
        ->assertDontSee('SQLSTATE');
});

it('enseña el mensaje de una regla de dominio, pero no los de Symfony en inglés', function (): void {
    $url = rutaQueFalla('panel/prueba-422', fn () => throw new DomainException('Esa solicitud ya fue aprobada.'));

    $this->get($url)
        ->assertStatus(422)
        ->assertSee('No se pudo hacer')
        ->assertSee('Esa solicitud ya fue aprobada.');

    // Abrir con GET lo que solo acepta POST: Symfony lo explica en inglés.
    $this->actingAs(User::factory()->docente()->create())->get(route('panel.rol-activo'))
        ->assertStatus(405)
        ->assertSee('No se pudo hacer')
        ->assertDontSee('method is not supported');
});

it('pregunta en español si la sesión caducó con una pantalla abierta', function (): void {
    // Livewire lo preguntaba en inglés («This page has expired»).
    $this->actingAs(User::factory()->docente()->create())->get(route('panel.inicio'))
        ->assertSee("Livewire.hook('request'", false)
        ->assertSee('Tu sesión caducó por inactividad');
});

it('deja libre lo fijo al llevar el foco con el teclado', function (): void {
    // WCAG 2.4.11: la barra del celular arriba y la de decisión abajo.
    $html = $this->actingAs(User::factory()->docente()->create())->get(route('panel.inicio'))->getContent();

    expect(Str::betweenFirst($html, '<html', '>'))->toContain('scroll-pt-[4.5rem]')->toContain('scroll-pb-40');
});
