<?php

declare(strict_types=1);

use App\Mail\AvisoFormatoIntramuralMail;
use App\Mail\ContrasenaCambiadaMail;
use App\Mail\RecuperarContrasenaMail;
use App\Mail\SalaAsignadaMail;
use App\Mail\SesionReprogramadaMail;
use App\Mail\SincronizacionDetenidaMail;
use App\Mail\SolicitudAprobadaMail;
use App\Mail\SolicitudRechazadaMail;
use App\Mail\SustitucionDocenteMail;
use App\Models\Solicitud;
use App\Models\User;
use App\Support\MuestrasDeCorreo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/*
 * correos:muestra manda un ejemplar de cada correo para verlo en un buzón
 * real (el sandbox) sin montar solicitudes ni salas de prueba. Los datos
 * se inventan en memoria: no se guarda nada.
 */

/** @return list<class-string> */
function correosDeLaAplicacion(): array
{
    return array_map(
        static fn (string $archivo): string => 'App\\Mail\\'.basename($archivo, '.php'),
        glob(app_path('Mail/*.php')) ?: [],
    );
}

it('envía exactamente una muestra de cada correo de la aplicación, en el acto y sin cola', function (): void {
    Mail::fake();

    $this->artisan('correos:muestra', ['email' => 'Prueba@Ejemplo.edu.co'])->assertSuccessful();

    foreach ([
        RecuperarContrasenaMail::class,
        ContrasenaCambiadaMail::class,
        SolicitudAprobadaMail::class,
        SolicitudRechazadaMail::class,
        SalaAsignadaMail::class,
        SesionReprogramadaMail::class,
        SustitucionDocenteMail::class,
        AvisoFormatoIntramuralMail::class,
        SincronizacionDetenidaMail::class,
    ] as $clase) {
        Mail::assertSent($clase, 1);
        Mail::assertSent($clase, fn ($correo): bool => $correo->hasTo('prueba@ejemplo.edu.co'));
    }

    Mail::assertSentCount(9);
    Mail::assertNothingQueued();
});

it('no se queda atrás cuando aparece un correo nuevo', function (): void {
    $muestras = array_map(get_class(...), array_values(app(MuestrasDeCorreo::class)->para('Persona')));

    expect($muestras)->toEqualCanonicalizing(correosDeLaAplicacion());
});

it('arma cada muestra completa, con el nombre de quien la recibe, sin consultar ni guardar nada en la base', function (): void {
    $consultas = 0;
    DB::listen(function () use (&$consultas): void {
        $consultas++;
    });

    $muestras = app(MuestrasDeCorreo::class)->para('Nombre De Prueba');

    foreach ($muestras as $correo) {
        expect($correo->render())->not->toBeEmpty()
            ->and($correo->envelope()->subject)->not->toBeEmpty();
    }

    expect($muestras['ContrasenaCambiadaMail']->render())->toContain('Nombre De Prueba');

    expect($consultas)->toBe(0)
        ->and(Solicitud::query()->count())->toBe(0);
});

it('saluda con el nombre de la cuenta cuando el correo ya tiene una', function (): void {
    Mail::fake();
    $cuenta = User::factory()->create(['email' => 'docente@ejemplo.edu.co']);

    $this->artisan('correos:muestra', ['email' => 'DOCENTE@ejemplo.edu.co'])->assertSuccessful();

    Mail::assertSent(ContrasenaCambiadaMail::class, fn (ContrasenaCambiadaMail $correo): bool => $correo->destinatario->nombre === $cuenta->nombre);
});

it('rechaza un correo mal escrito sin enviar nada', function (): void {
    Mail::fake();

    $this->artisan('correos:muestra', ['email' => 'no-es-un-correo'])->assertFailed();

    Mail::assertNothingSent();
});
