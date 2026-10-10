<?php

declare(strict_types=1);

use App\Enums\ClaveConfiguracionLanding;
use App\Models\Capacidad;
use App\Models\CasoClinico;
use App\Models\Certificacion;
use App\Models\ConfiguracionLanding;
use App\Models\EquipoDestacado;
use App\Models\EstadisticaLanding;
use App\Models\Evento;
use App\Models\GaleriaFoto;
use App\Models\ItemInventario;
use App\Models\PerfilDocente;
use App\Models\Taller;
use App\Models\TituloDocente;
use App\Services\ImagenPublicaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
 * La portada pública (RF01–RF07) y el detalle de cada escenario (RF03).
 * Todo lo que muestra lo carga el ADMIN; aquí se comprueba que sale lo
 * publicado, que no sale lo demás y que nada se rompe sin contenido.
 */

beforeEach(function (): void {
    Storage::fake(ImagenPublicaService::DISCO);
});

/** Deja un archivo en el disco público, como si el ADMIN lo hubiera subido. */
function archivoPublico(string $ruta): string
{
    Storage::disk(ImagenPublicaService::DISCO)->put($ruta, 'x');

    return $ruta;
}

function configurarPortada(ClaveConfiguracionLanding $clave, string $valor): void
{
    ConfiguracionLanding::create(['clave' => $clave->value, 'valor' => $valor]);
}

// ---------------------------------------------------------------------
// Sin contenido y con contenido
// ---------------------------------------------------------------------

it('se abre sin contenido cargado, con el nombre del laboratorio y sin secciones vacías', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('<html lang="es">', false)
        ->assertSee(config('app.name'))
        ->assertSee('Vigilada Mineducación')
        ->assertDontSee('En cifras.')
        ->assertDontSee('Escenarios que se viven.')
        ->assertDontSee('Quienes te acompañan.');
});

it('muestra el titular y el subtítulo que configuró el ADMIN', function (): void {
    configurarPortada(ClaveConfiguracionLanding::HeroTitulo, 'Simulación que se siente real.');
    configurarPortada(ClaveConfiguracionLanding::HeroSubtitulo, 'Practica antes de llegar al paciente.');

    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder(['Simulación que se siente real.', 'Practica antes de llegar al paciente.']);
});

it('muestra las cifras con su valor real en el HTML, listas para contar', function (): void {
    EstadisticaLanding::factory()->create(['etiqueta' => 'Estudiantes', 'valor' => '+700', 'orden' => 1]);
    EstadisticaLanding::factory()->create(['etiqueta' => 'Oculta', 'valor' => '99', 'activo' => false]);

    $respuesta = $this->get('/')->assertOk()->assertSee('En cifras.')->assertSee('Estudiantes');

    // El valor está en el HTML aunque el JavaScript no cargue.
    expect($respuesta->getContent())->toContain('data-contador>+700</span>')
        ->and($respuesta->getContent())->toContain('<span class="sr-only">+700</span>')
        ->and($respuesta->getContent())->not->toContain('Oculta');
});

it('presenta el equipamiento destacado con sus características, el primero como principal', function (): void {
    EquipoDestacado::factory()->create(['nombre' => 'Sala inmersiva', 'orden' => 2]);
    EquipoDestacado::factory()->create([
        'nombre' => 'Simulador de alta fidelidad',
        'resumen' => 'Respira y responde como un paciente real.',
        'caracteristicas' => ['Signos vitales en tiempo real', 'Sangrado'],
        'orden' => 1,
    ]);
    EquipoDestacado::factory()->create(['nombre' => 'Equipo retirado', 'activo' => false]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Practicar sin riesgo.')
        ->assertSeeInOrder(['Simulador de alta fidelidad', 'Respira y responde como un paciente real.', 'Signos vitales en tiempo real', 'Sala inmersiva'])
        ->assertDontSee('Equipo retirado');
});

it('muestra solo los escenarios publicados, con sus capacidades y el enlace al detalle', function (): void {
    $publicado = CasoClinico::factory()->visibleEnPublico()->create(['nombre' => 'Atención de parto normal']);
    $publicado->capacidades()->attach(Capacidad::factory()->create(['nombre' => 'Sangrado']));
    CasoClinico::factory()->create(['nombre' => 'Escenario interno', 'visible_publico' => false]);
    CasoClinico::factory()->visibleEnPublico()->create(['nombre' => 'Escenario inactivo', 'activo' => false]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Atención de parto normal')
        ->assertSee('Sangrado')
        ->assertSee(route('portada.escenario', $publicado), false)
        ->assertDontSee('Escenario interno')
        ->assertDontSee('Escenario inactivo');
});

it('anuncia los talleres y eventos de hoy en adelante, y no los que ya pasaron', function (): void {
    $this->travelTo('2026-10-10 09:00:00');
    Taller::factory()->create(['titulo' => 'Taller de RCP', 'fecha' => '2026-10-10']);
    Taller::factory()->create(['titulo' => 'Taller que ya pasó', 'fecha' => '2026-10-09']);
    Evento::factory()->create(['titulo' => 'Jornada de simulación', 'fecha' => '2026-11-03', 'abierto_publico' => true]);
    Evento::factory()->create(['titulo' => 'Congreso pasado', 'fecha' => '2026-09-01']);

    $this->get('/')
        ->assertOk()
        ->assertSee('Siempre hay algo por aprender.')
        ->assertSee('Taller de RCP')
        ->assertSee('10 de octubre de 2026')
        ->assertSee('Jornada de simulación')
        ->assertSee('Abierto al público')
        ->assertDontSee('Taller que ya pasó')
        ->assertDontSee('Congreso pasado');
});

it('ofrece pedir información de un taller por correo mientras no exista el formulario', function (): void {
    configurarPortada(ClaveConfiguracionLanding::ContactoEmail, 'laboratorio@ufps.edu.co');
    Taller::factory()->create(['titulo' => 'Taller de RCP']);

    $this->get('/')
        ->assertOk()
        ->assertSee('mailto:laboratorio@ufps.edu.co?subject='.rawurlencode('Información: Taller de RCP'), false);
});

it('muestra certificaciones y docentes con sus títulos', function (): void {
    Certificacion::factory()->create(['nombre' => 'Centro de entrenamiento certificado', 'entidad' => 'American Heart Association']);
    $docente = PerfilDocente::factory()->create(['nombre' => 'Claudia Patricia Ríos', 'cargo' => 'Coordinadora del laboratorio']);
    TituloDocente::factory()->create(['perfil_docente_id' => $docente->id, 'titulo' => 'Magíster en educación para la salud']);

    $this->get('/')
        ->assertOk()
        ->assertSee('Centro de entrenamiento certificado')
        // Sin imagen, la insignia lleva las siglas de la entidad.
        ->assertSee('AHA')
        ->assertSee('Claudia Patricia Ríos')
        ->assertSee('Coordinadora del laboratorio')
        ->assertSee('Magíster en educación para la salud');
});

it('muestra el contacto con enlaces que funcionan', function (): void {
    configurarPortada(ClaveConfiguracionLanding::ContactoTelefono, '+57 607 577 6655');
    configurarPortada(ClaveConfiguracionLanding::ContactoEmail, 'laboratorio@ufps.edu.co');

    $this->get('/')
        ->assertOk()
        ->assertSee('Ven a conocerlo.')
        ->assertSee('href="tel:+576075776655"', false)
        ->assertSee('href="mailto:laboratorio@ufps.edu.co"', false);
});

// ---------------------------------------------------------------------
// Imágenes y video
// ---------------------------------------------------------------------

it('trata una imagen cuyo archivo no está como si no hubiera foto', function (): void {
    EquipoDestacado::factory()->create(['nombre' => 'Con foto', 'imagen' => archivoPublico('equipamiento/con-foto.webp'), 'orden' => 1]);
    EquipoDestacado::factory()->create(['nombre' => 'Foto perdida', 'imagen' => 'equipamiento/borrada.webp', 'orden' => 2]);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('equipamiento/con-foto.webp')
        ->and($html)->not->toContain('equipamiento/borrada.webp');
});

it('solo pone en la galería las fotos que existen en el disco', function (): void {
    GaleriaFoto::factory()->create(['titulo' => 'Sala de partos', 'imagen_path' => archivoPublico('landing/galeria/partos.webp')]);
    GaleriaFoto::factory()->create(['titulo' => 'Foto sin archivo', 'imagen_path' => 'landing/galeria/no-existe.webp']);

    $this->get('/')
        ->assertOk()
        ->assertSee('Por dentro.')
        ->assertSee('landing/galeria/partos.webp', false)
        ->assertDontSee('no-existe.webp', false)
        ->assertDontSee('Foto sin archivo');
});

it('pone el video del hero sin reproducción automática en el HTML, con su tipo y su botón de pausa', function (): void {
    configurarPortada(ClaveConfiguracionLanding::HeroVideo, archivoPublico('landing/recorrido.webm'));

    $html = $this->get('/')->assertOk()->getContent();

    // Lo arranca portada.js, que respeta prefers-reduced-motion.
    expect($html)->toContain('landing/recorrido.webm#t=0.1')
        ->and($html)->toContain('type="video/webm"')
        ->and($html)->toContain('data-video-control')
        ->and($html)->not->toContain('autoplay');
});

it('no pone el video si el archivo no está', function (): void {
    configurarPortada(ClaveConfiguracionLanding::HeroVideo, 'landing/borrado.mp4');

    expect($this->get('/')->assertOk()->getContent())->not->toContain('<video');
});

// ---------------------------------------------------------------------
// Composición
// ---------------------------------------------------------------------

it('alterna los fondos entre las secciones que existen, aunque falte alguna', function (): void {
    EstadisticaLanding::factory()->create();
    // Sin equipamiento: escenarios es la segunda sección y va en blanco.
    CasoClinico::factory()->visibleEnPublico()->create();
    Certificacion::factory()->create();

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toMatch('/id="cifras"[^>]*fondo-niebla/')
        ->and($html)->toMatch('/id="escenarios"[^>]*fondo-blanco/')
        ->and($html)->toMatch('/id="certificaciones"[^>]*fondo-niebla/');
});

it('enlaza en la cabecera solo las secciones que existen', function (): void {
    CasoClinico::factory()->visibleEnPublico()->create();

    $this->get('/')
        ->assertOk()
        ->assertSee('href="#escenarios"', false)
        ->assertDontSee('href="#docentes"', false)
        ->assertDontSee('href="#equipamiento"', false);
});

it('no carga nada de otros sitios: fuente y logos salen del propio servidor', function (): void {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('fonts/onest/onest-latin.woff2')
        ->and($html)->toContain('marca/ufps-simbolo.webp')
        ->and($html)->not->toContain('fonts.googleapis.com')
        ->and($html)->not->toContain('fonts.bunny.net')
        ->and($html)->not->toContain('laravel.com');
});

it('hace las mismas consultas con dos o con seis piezas por sección', function (): void {
    $sembrar = function (int $cuantas): void {
        EstadisticaLanding::factory()->count($cuantas)->create();
        EquipoDestacado::factory()->count($cuantas)->create();
        CasoClinico::factory()->visibleEnPublico()->count($cuantas)->create()
            ->each(fn (CasoClinico $caso) => $caso->capacidades()->attach(Capacidad::factory()->create()));
        Taller::factory()->count($cuantas)->create();
        Evento::factory()->count($cuantas)->create();
        Certificacion::factory()->count($cuantas)->create();
        PerfilDocente::factory()->count($cuantas)->create()
            ->each(fn (PerfilDocente $docente) => TituloDocente::factory()->create(['perfil_docente_id' => $docente->id]));
    };

    $medir = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/')->assertOk();
        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $total;
    };

    $sembrar(2);
    $conDos = $medir();
    $sembrar(4);

    expect($medir())->toBe($conDos);
});

// ---------------------------------------------------------------------
// Detalle del escenario (RF03)
// ---------------------------------------------------------------------

it('abre el detalle de un escenario publicado con sus capacidades y su equipo', function (): void {
    $escenario = CasoClinico::factory()->visibleEnPublico()->create([
        'nombre' => 'Atención de parto normal',
        'descripcion' => 'Atención del trabajo de parto y del recién nacido.',
    ]);
    $escenario->capacidades()->attach(Capacidad::factory()->create(['nombre' => 'Llanto']));
    $escenario->items()->attach(ItemInventario::factory()->simulador()->create(['nombre' => 'Maniquí de parto'])->id, ['cantidad' => 1]);

    $this->get(route('portada.escenario', $escenario))
        ->assertOk()
        ->assertSee('<title>Atención de parto normal', false)
        ->assertSee('Atención del trabajo de parto y del recién nacido.')
        ->assertSee('Llanto')
        ->assertSee('Maniquí de parto')
        ->assertSee(route('panel.solicitudes.nueva'), false);
});

it('no abre el detalle de un escenario que no está publicado', function (array $estado): void {
    $escenario = CasoClinico::factory()->create($estado);

    $this->get(route('portada.escenario', $escenario))->assertNotFound();
})->with([
    'oculto al público' => [['visible_publico' => false, 'activo' => true]],
    'inactivo' => [['visible_publico' => true, 'activo' => false]],
]);

it('sugiere hasta tres escenarios más, sin repetir el que se está viendo', function (): void {
    $escenarios = CasoClinico::factory()->visibleEnPublico()->count(5)
        ->sequence(fn ($secuencia) => ['nombre' => 'Escenario '.($secuencia->index + 1), 'orden' => $secuencia->index + 1])
        ->create();

    $html = $this->get(route('portada.escenario', $escenarios[0]))->assertOk()->getContent();
    $otros = substr($html, (int) strpos($html, 'Otros escenarios.'));

    expect($otros)->not->toContain('Escenario 1<')
        ->and($otros)->toContain('Escenario 2')
        ->and($otros)->toContain('Escenario 4')
        ->and($otros)->not->toContain('Escenario 5');
});
