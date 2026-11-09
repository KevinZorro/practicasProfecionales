<?php

declare(strict_types=1);

use App\Mail\AvisoFormatoIntramuralMail;
use App\Mail\SalaAsignadaMail;
use App\Mail\SolicitudAprobadaMail;
use App\Mail\SolicitudRechazadaMail;
use App\Models\Preparacion;
use App\Models\Sala;
use App\Models\Solicitud;
use App\Models\User;
use Database\Seeders\RolSeeder;

/*
 * Cada correo de una sesión lleva a la pantalla donde se sigue: sin enlace,
 * el docente tenía que entrar, buscar la solicitud y adivinar cuál era.
 * Dice «Escenario clínico», como el formulario donde lo eligió, y las horas
 * sin segundos.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
});

/** Una solicitud de 07:00 a 09:00, con lo que pintan los correos. */
function solicitudDelCorreo(array $atributos = []): Solicitud
{
    return Solicitud::factory()->aprobada()->create(['hora_inicio' => '07:00:00', 'hora_fin' => '09:00:00', ...$atributos]);
}

it('lleva a la solicitud desde el correo de aprobación y desde el de rechazo', function (): void {
    $aprobada = solicitudDelCorreo();
    $rechazada = Solicitud::factory()->rechazada()->create(['hora_inicio' => '07:00:00', 'hora_fin' => '09:00:00']);

    expect((new SolicitudAprobadaMail($aprobada))->render())
        ->toContain('href="'.route('panel.solicitudes.ver', $aprobada).'"')
        ->toContain('Ver la solicitud')
        ->and((new SolicitudRechazadaMail($rechazada))->render())
        ->toContain('href="'.route('panel.solicitudes.ver', $rechazada).'"');
});

it('lleva a la sesión desde el correo de la sala asignada', function (): void {
    $solicitud = solicitudDelCorreo();
    $preparacion = Preparacion::factory()->create(['solicitud_id' => $solicitud->id]);
    $preparacion->salas()->attach(Sala::factory()->create()->id);

    expect((new SalaAsignadaMail($preparacion->fresh(), esCambio: false))->render())
        ->toContain('href="'.route('panel.solicitudes.ver', $solicitud).'"')
        ->toContain('Ver la sesión');
});

it('dice «Escenario clínico», como el formulario, y las horas sin segundos', function (): void {
    $solicitud = solicitudDelCorreo();
    $preparacion = Preparacion::factory()->create(['solicitud_id' => $solicitud->id]);
    $preparacion->salas()->attach(Sala::factory()->create()->id);

    foreach ([new SolicitudAprobadaMail($solicitud), new SolicitudRechazadaMail($solicitud), new SalaAsignadaMail($preparacion->fresh(), esCambio: true)] as $correo) {
        expect($correo->render())
            ->toContain('Escenario clínico')
            ->not->toContain('Caso clínico')
            ->toContain('07:00 a 09:00')
            ->not->toContain('07:00:00');
    }
});

it('lleva cada sesión sin formato intramural a su expediente, y a quien registra, a sus sesiones apartadas', function (): void {
    $sesion = solicitudDelCorreo();
    $sesiones = Solicitud::query()->whereKey($sesion->id)->get();
    $administrativo = User::factory()->administrativo()->create();

    expect((new AvisoFormatoIntramuralMail($administrativo, $sesiones, paraDocente: false))->render())
        ->toContain('href="'.route('panel.solicitudes.ver', $sesion).'"')
        ->toContain('href="'.route('panel.sesiones-apartadas').'"')
        ->and((new AvisoFormatoIntramuralMail($sesion->docente, $sesiones, paraDocente: true))->render())
        ->toContain('href="'.route('panel.solicitudes.ver', $sesion).'"')
        ->not->toContain(route('panel.sesiones-apartadas'));
});

it('vuelve a la solicitud del enlace después de entrar, si no había sesión abierta', function (): void {
    $solicitud = solicitudDelCorreo();

    $this->get(route('panel.solicitudes.ver', $solicitud))->assertRedirect();

    // Lo que AccesoConGoogleController usa al volver de Google.
    expect(session('url.intended'))->toBe(route('panel.solicitudes.ver', $solicitud));
});
