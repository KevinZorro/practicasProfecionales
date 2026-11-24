<?php

declare(strict_types=1);
use App\Models\User;
use Database\Seeders\RolSeeder;

/*
 * Los enlaces y los assets salen de APP_URL cuando APP_URL_FIJA está
 * activa (middleware UsarLaDireccionConfigurada). Con un túnel SSH a
 * localhost:8080 la petición llegaba con `Host: localhost`, la página pedía
 * sus estilos a http://localhost y la CSP los bloqueaba.
 */

it('con la dirección fija, los enlaces usan APP_URL aunque el Host llegue sin el puerto', function (): void {
    config(['app.url' => 'http://localhost:8080', 'app.url_fija' => true]);

    $this->get('http://localhost/')
        ->assertOk()
        ->assertSee('href="http://localhost:8080/favicon.ico"', escape: false)
        ->assertDontSee('href="http://localhost/favicon.ico"', escape: false);
});

it('con una dirección https, los enlaces salen por https aunque la petición llegue por http', function (): void {
    config(['app.url' => 'https://laboratorio.ejemplo.edu.co', 'app.url_fija' => true]);

    $this->get('http://172.18.222.233/')
        ->assertOk()
        ->assertSee('href="https://laboratorio.ejemplo.edu.co/favicon.ico"', escape: false);
});

it('sin la dirección fija, los enlaces siguen el Host de la petición', function (): void {
    config(['app.url' => 'http://localhost:8080', 'app.url_fija' => false]);

    $this->get('http://otro-nombre/')
        ->assertOk()
        ->assertSee('href="http://otro-nombre/favicon.ico"', escape: false);
});

it('con la dirección fija, volver a la página pedida tras entrar conserva el puerto de APP_URL', function (): void {
    $this->seed(RolSeeder::class);
    config(['app.url' => 'http://localhost:8080', 'app.url_fija' => true, 'laboratorio.acceso.con_contrasena' => true]);
    $docente = User::factory()->docente()->create();
    $docente->forceFill(['password' => 'una-contrasena-larga'])->save();

    // El invitado pide el panel por el túnel: el Host llega sin el puerto.
    $this->get('http://localhost/panel')->assertRedirect('http://localhost:8080/acceso');

    $this->post('http://localhost/acceso', ['email' => $docente->email, 'contrasena' => 'una-contrasena-larga'])
        ->assertRedirect('http://localhost:8080/panel');
});

it('con la dirección fija, «volver atrás» también usa APP_URL', function (): void {
    config(['app.url' => 'http://localhost:8080', 'app.url_fija' => true, 'laboratorio.acceso.con_contrasena' => true]);

    $this->get('http://localhost/acceso');

    $this->post('http://localhost/acceso', ['email' => 'no-es-un-correo', 'contrasena' => 'x'])
        ->assertRedirect('http://localhost:8080/acceso');
});

it('con la dirección fija, el panel del ADMIN pide su fuente y su favicon a APP_URL aunque el Host llegue sin el puerto', function (): void {
    $this->seed(RolSeeder::class);
    // Como en el servidor: el panel de Filament se arma al arrancar, con la
    // dirección que tenía la petición, y la fija se aplica después.
    config(['app.url' => 'http://localhost:8080', 'app.url_fija' => true]);

    $html = $this->actingAs(User::factory()->admin()->create())
        ->get('http://localhost/admin')
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('href="http://localhost:8080/fonts/onest/onest.css"')
        ->toContain('href="http://localhost:8080/favicon.ico"')
        ->not->toContain('http://localhost/fonts/')
        ->not->toContain('http://localhost/favicon.ico');
});

it('con la dirección fija, el generador de enlaces usa APP_URL desde que se crea, antes de cualquier middleware', function (): void {
    config(['app.url' => 'http://localhost:8080', 'app.url_fija' => true]);

    // Lo que hace el arranque: crear el generador con la petición tal como
    // llegó. Lo que un proveedor arme ahí ya tiene que salir con APP_URL.
    app()->forgetInstance('url');

    expect(asset('favicon.ico'))->toBe('http://localhost:8080/favicon.ico')
        ->and(url('/panel'))->toBe('http://localhost:8080/panel');
});
