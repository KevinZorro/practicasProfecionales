<?php

declare(strict_types=1);

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
