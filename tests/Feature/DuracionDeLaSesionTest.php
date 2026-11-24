<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolSeeder;

/*
 * Una sesión vence con 60 minutos sin actividad (SESSION_LIFETIME) y,
 * aunque se use sin parar, a las 24 horas de haber entrado
 * (CerrarSesionesLargas, NIST SP 800-63B).
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    config(['laboratorio.acceso.con_contrasena' => true]);
});

it('el valor por defecto de SESSION_LIFETIME es 60 minutos', function (): void {
    $archivo = (string) file_get_contents(config_path('session.php'));

    expect($archivo)->toContain("env('SESSION_LIFETIME', 60)");
    expect((string) file_get_contents(base_path('.env.example')))->toContain('SESSION_LIFETIME=60');
    expect((string) file_get_contents(base_path('.env.nativo.example')))->toContain('SESSION_LIFETIME=60');
});

it('a las 24 horas de haber entrado la sesión se cierra aunque se use sin parar', function (): void {
    $this->actingAs(User::factory()->docente()->create());
    $this->get(route('panel.inicio'))->assertOk();

    // Usada cada hora durante casi un día: la inactividad nunca la corta.
    foreach (range(1, 23) as $hora) {
        $this->travel(1)->hours();
        $this->get(route('panel.inicio'))->assertOk();
    }

    $this->travel(1)->hours();
    $this->get(route('panel.inicio'))->assertRedirect(route('acceso'));
    $this->assertGuest();

    $this->get(route('acceso'))->assertSee('a las 24 horas de haber entrado');
});

it('volver a entrar empieza a contar otra vez', function (): void {
    $docente = User::factory()->docente()->create();
    $docente->forceFill(['password' => 'una-contrasena-larga'])->save();

    $this->post(route('acceso.entrar'), ['email' => $docente->email, 'contrasena' => 'una-contrasena-larga']);
    $this->get(route('panel.inicio'))->assertOk();
    $this->travel(25)->hours();
    $this->get(route('panel.inicio'))->assertRedirect(route('acceso'));

    $this->post(route('acceso.entrar'), ['email' => $docente->email, 'contrasena' => 'una-contrasena-larga'])
        ->assertRedirect(route('panel.inicio'));
    $this->get(route('panel.inicio'))->assertOk();
    $this->travel(23)->hours();
    $this->get(route('panel.inicio'))->assertOk();
});

it('a un invitado no le hace nada', function (): void {
    $this->get(route('inicio.publico'))->assertOk();
    $this->travel(48)->hours();
    $this->get(route('inicio.publico'))->assertOk();
});
