<?php

declare(strict_types=1);

use App\Mail\SolicitudRechazadaMail;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\ConfiguracionLandingService;
use Database\Seeders\RolSeeder;

/*
 * Donde la plataforma dice «comunícate con el laboratorio», dice cómo: el
 * correo y el teléfono que el administrador carga con la portada (RF11).
 * La ayuda va siempre en el mismo sitio, al pie de cada pantalla del panel
 * (WCAG 3.2.6).
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
});

function cargarContacto(?string $correo = 'simulacion@ufps.edu.co', ?string $telefono = '+57 607 577 6655'): void
{
    app(ConfiguracionLandingService::class)->guardar(['contacto_email' => $correo, 'contacto_telefono' => $telefono]);
}

it('dice al pie de cada pantalla del panel cómo escribir o llamar al laboratorio', function (string $ruta): void {
    cargarContacto();

    $this->actingAs(User::factory()->docente()->create())->get(route($ruta))
        ->assertOk()
        ->assertSee('¿Dudas?')
        ->assertSee('href="mailto:simulacion@ufps.edu.co"', false)
        ->assertSee('href="tel:+576075776655"', false);
})->with(['panel.inicio', 'panel.calendario', 'panel.mis-solicitudes']);

it('dice solo lo que el laboratorio cargó', function (): void {
    cargarContacto(correo: null);

    $this->actingAs(User::factory()->docente()->create())->get(route('panel.inicio'))
        ->assertSee('Llama al laboratorio al')
        ->assertDontSee('mailto:', false);
});

it('no promete un contacto que nadie cargó', function (): void {
    $this->actingAs(User::factory()->docente()->create())->get(route('panel.inicio'))
        ->assertOk()
        ->assertDontSee('¿Dudas?')
        ->assertDontSee('Escribe al laboratorio');
});

it('dice cómo comunicarse con el laboratorio en el 403', function (): void {
    cargarContacto();

    $this->actingAs(User::factory()->docente()->create())->get(route('panel.reportes'))
        ->assertForbidden()
        ->assertSee('¿Crees que es un error?')
        ->assertSee('simulacion@ufps.edu.co');
});

it('firma los correos con el contacto del laboratorio', function (): void {
    cargarContacto();
    $rechazada = Solicitud::factory()->rechazada()->create(['motivo_rechazo' => null]);

    expect((new SolicitudRechazadaMail($rechazada))->render())
        ->toContain('Puede consultarlo con el laboratorio')
        ->toContain('href="mailto:simulacion@ufps.edu.co"')
        ->toContain('+57 607 577 6655');
});
