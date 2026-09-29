<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolSeeder;

/*
 * Cabeceras de seguridad de todas las respuestas (middleware
 * CabecerasDeSeguridad).
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
});

it('manda las cabeceras de seguridad en la portada', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
        ->assertHeader('Permissions-Policy');
});

it('las manda también en el panel, en Filament y en una página de error', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    foreach ([route('panel.inicio'), '/admin', '/no-existe'] as $url) {
        $respuesta = $this->get($url);

        expect($respuesta->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN', $url)
            ->and($respuesta->headers->get('Content-Security-Policy'))->not->toBeNull($url);
    }
});

it('cierra en la política de contenido lo que no hace falta abrir', function (): void {
    $politica = (string) $this->get('/')->headers->get('Content-Security-Policy');

    expect($politica)->toContain("default-src 'self'")
        ->toContain("object-src 'none'")
        ->toContain("base-uri 'self'")
        ->toContain("form-action 'self'")
        ->toContain("frame-ancestors 'self'")
        // Lo mínimo que abre, y por qué está en el middleware.
        ->toContain("worker-src 'self' blob:")
        // Ningún origen comodín: un "*" o un "https:" abriría la puerta a
        // cualquier sitio.
        ->not->toMatch('/(^|\s)\*(\s|;|$)/')
        ->not->toMatch('/(^|\s)https:(\s|;|$)/');
});

it('deja apagar la política de contenido sin tocar código', function (): void {
    config(['seguridad.politica_de_contenido' => false]);

    $this->get('/')
        ->assertHeaderMissing('Content-Security-Policy')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
});

it('no manda HSTS sobre HTTP', function (): void {
    $this->get('http://localhost/')->assertHeaderMissing('Strict-Transport-Security');
});

it('manda HSTS sobre HTTPS, sin obligar a los subdominios de la universidad', function (): void {
    $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
});

it('reconoce el HTTPS que termina un proxy de confianza', function (): void {
    // El proxy de la universidad recibe HTTPS y reenvía por HTTP.
    config(['trustedproxy.proxies' => '10.0.0.5']);

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
        ->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->get('http://localhost/')
        ->assertHeader('Strict-Transport-Security');
});

it('no se cree el X-Forwarded-Proto de quien no es un proxy de confianza', function (): void {
    config(['trustedproxy.proxies' => '10.0.0.5']);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->get('http://localhost/')
        ->assertHeaderMissing('Strict-Transport-Security');
});

it('no confía en ningún proxy si no se configuró', function (): void {
    expect(config('trustedproxy.proxies'))->toBeNull();

    $this->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->get('http://localhost/')
        ->assertHeaderMissing('Strict-Transport-Security');
});
