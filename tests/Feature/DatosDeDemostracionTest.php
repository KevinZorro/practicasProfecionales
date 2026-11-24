<?php

declare(strict_types=1);

use App\Enums\EstadoSolicitud;
use App\Enums\Rol;
use App\Mail\AvisoFormatoIntramuralMail;
use App\Mail\SalaAsignadaMail;
use App\Mail\SesionReprogramadaMail;
use App\Mail\SolicitudAprobadaMail;
use App\Mail\SolicitudRechazadaMail;
use App\Mail\SustitucionDocenteMail;
use App\Models\CasoClinico;
use App\Models\Materia;
use App\Models\Preparacion;
use App\Models\RegistroDeBitacora;
use App\Models\Sala;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\DatosReprogramacion;
use App\Services\DemostracionService;
use App\Services\NovedadesDeSesionService;
use App\Services\PreparacionService;
use App\Services\RegistroPrevioService;
use App\Services\SolicitudService;
use Database\Seeders\RolSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/*
 * demo:preparar deja en el sandbox lo justo para probar a mano el flujo de
 * una solicitud y cada correo que dispara. Convive con datos reales: no
 * manda nada al crearse y --limpiar borra solo lo suyo.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
});

function cuentaDemo(string $email): User
{
    return User::query()->where('email', $email)->firstOrFail();
}

/** @return array<string, Solicitud> */
function solicitudesDemo(): array
{
    return app(DemostracionService::class)->preparar('kevin@ejemplo.com')->solicitudes;
}

it('crea las cuentas como alias del correo, con su rol, y una solicitud por cada camino del flujo, sin mandar nada', function (): void {
    Mail::fake();

    $this->artisan('demo:preparar', ['email' => 'Kevin+viejo@Ejemplo.com'])
        ->expectsOutputToContain('kevin+docente@ejemplo.com')
        ->assertSuccessful();

    expect(cuentaDemo('kevin+docente@ejemplo.com')->hasRole(Rol::Docente->value))->toBeTrue()
        ->and(cuentaDemo('kevin+docente2@ejemplo.com')->hasRole(Rol::Docente->value))->toBeTrue()
        ->and(cuentaDemo('kevin+admvo@ejemplo.com')->hasRole(Rol::Administrativo->value))->toBeTrue()
        ->and(cuentaDemo('kevin+coord@ejemplo.com')->hasRole(Rol::Coordinador->value))->toBeTrue()
        ->and(User::role(Rol::Estudiante->value)->count())->toBe(4);

    $estados = Solicitud::query()->orderBy('fecha')->pluck('estado')->map->value->all();
    expect($estados)->toBe(['aprobada', 'pendiente', 'pendiente', 'revisada', 'aprobada'])
        ->and(CasoClinico::query()->count())->toBe(4)
        ->and(CasoClinico::query()->where('visible_publico', true)->exists())->toBeFalse()
        ->and(Sala::query()->count())->toBe(2);

    Mail::assertNothingSent();
    Mail::assertNothingQueued();
});

it('las contraseñas que muestra sirven para entrar', function (): void {
    $hecha = app(DemostracionService::class)->preparar('kevin@ejemplo.com');

    foreach ($hecha->cuentas as $cuenta) {
        expect(Hash::check($cuenta->contrasena, cuentaDemo($cuenta->email)->password))->toBeTrue();
    }
});

it('repetirlo lo deja como recién hecho, sin duplicar nada y con contraseñas nuevas', function (): void {
    $primera = app(DemostracionService::class)->preparar('kevin@ejemplo.com');
    $segunda = app(DemostracionService::class)->preparar('kevin@ejemplo.com');

    expect(User::query()->count())->toBe(8)
        ->and(Solicitud::query()->count())->toBe(5)
        ->and(CasoClinico::query()->count())->toBe(4)
        ->and(Materia::query()->count())->toBe(2)
        ->and(Sala::query()->count())->toBe(2)
        ->and($segunda->cuentas['docente']->contrasena)->not->toBe($primera->cuentas['docente']->contrasena);
});

it('en producción no hace nada sin --en-produccion', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('demo:preparar', ['email' => 'kevin@ejemplo.com'])->assertFailed();
    expect(User::query()->count())->toBe(0);

    $this->artisan('demo:preparar', ['email' => 'kevin@ejemplo.com', '--en-produccion' => true])->assertSuccessful();
    expect(Solicitud::query()->count())->toBe(5);
});

it('pide un correo válido', function (): void {
    $this->artisan('demo:preparar')->assertFailed();
    $this->artisan('demo:preparar', ['email' => 'no-es-correo'])->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('cada paso de la guía manda su correo, exactamente una vez, al alias que toca', function (): void {
    Mail::fake();
    $solicitudes = solicitudesDemo();
    $admvo = cuentaDemo('kevin+admvo@ejemplo.com');
    $coord = cuentaDemo('kevin+coord@ejemplo.com');
    $servicio = app(SolicitudService::class);

    $servicio->marcarRevisada($solicitudes['para_aprobar'], $admvo);
    $servicio->aprobar($solicitudes['para_aprobar']->refresh(), $coord);
    $servicio->rechazar($solicitudes['para_rechazar'], $admvo, 'No hay simulador ese día.');

    $aprobada = $solicitudes['aprobada'];
    $sala = Sala::query()->where('codigo', 'DEMO-S1')->firstOrFail();
    app(PreparacionService::class)->asignarSalas(Preparacion::query()->where('solicitud_id', $aprobada->id)->firstOrFail(), [$sala->id]);
    app(NovedadesDeSesionService::class)->reprogramar($aprobada->refresh(), new DatosReprogramacion(
        fecha: $aprobada->fecha->copy()->addDay()->toDateString(),
        horaInicio: '14:00',
        horaFin: '16:00',
        casoClinicoId: $aprobada->caso_clinico_id,
        motivo: 'Cambio de horario.',
        constanciaComunicacion: 'Se habló por teléfono.',
    ), $admvo);
    app(NovedadesDeSesionService::class)->sustituir($aprobada->refresh(), cuentaDemo('kevin+docente2@ejemplo.com'), 'Incapacidad.', $admvo);
    app(RegistroPrevioService::class)->avisarSinFormatoIntramural();

    // Los de los listeners salen con send(): el listener ya corre en la cola.
    $docente = 'kevin+docente@ejemplo.com';
    Mail::assertSent(SolicitudAprobadaMail::class, 1);
    Mail::assertSent(SolicitudAprobadaMail::class, fn ($correo): bool => $correo->hasTo($docente));
    Mail::assertSent(SolicitudRechazadaMail::class, 1);
    Mail::assertSent(SolicitudRechazadaMail::class, fn ($correo): bool => $correo->hasTo($docente));
    Mail::assertSent(SalaAsignadaMail::class, 1);
    Mail::assertQueued(SesionReprogramadaMail::class, 1);
    Mail::assertQueued(SustitucionDocenteMail::class, 1);
    Mail::assertQueued(SustitucionDocenteMail::class, fn ($correo): bool => $correo->hasTo('kevin+docente2@ejemplo.com'));
    // Al docente y al administrativo de la demostración.
    Mail::assertQueued(AvisoFormatoIntramuralMail::class, 2);
    Mail::assertQueued(AvisoFormatoIntramuralMail::class, fn ($correo): bool => $correo->hasTo($docente));
    Mail::assertQueued(AvisoFormatoIntramuralMail::class, fn ($correo): bool => $correo->hasTo('kevin+admvo@ejemplo.com'));
});

it('--limpiar borra solo lo de la demostración, con su rastro en la bitácora', function (): void {
    $real = Solicitud::factory()->create();
    $salaReal = Sala::factory()->create();
    $solicitudes = solicitudesDemo();
    app(SolicitudService::class)->rechazar($solicitudes['para_rechazar'], cuentaDemo('kevin+admvo@ejemplo.com'), 'Prueba.');

    $this->artisan('demo:preparar', ['--limpiar' => true])
        ->expectsOutputToContain('5 solicitudes y 8 cuentas')
        ->assertSuccessful();

    expect(Solicitud::query()->pluck('id')->all())->toBe([$real->id])
        ->and(User::query()->whereKey([$real->docente_id])->exists())->toBeTrue()
        ->and(User::query()->where('codigo_institucional', 'like', 'DEMO-%')->exists())->toBeFalse()
        ->and(CasoClinico::query()->pluck('id')->all())->toBe([$real->caso_clinico_id])
        ->and(Materia::query()->pluck('id')->all())->toBe([$real->materia_id])
        ->and(Sala::query()->pluck('id')->all())->toBe([$salaReal->id])
        ->and(RegistroDeBitacora::query()->count())->toBe(0);
});

it('una cuenta de la demostración que actuó sobre datos reales queda deshabilitada, no se borra', function (): void {
    solicitudesDemo();
    $coord = cuentaDemo('kevin+coord@ejemplo.com');
    $real = Solicitud::factory()->create([
        'estado' => EstadoSolicitud::Rechazada,
        'resuelta_por' => $coord->id,
        'resuelta_at' => now(),
    ]);

    $this->artisan('demo:preparar', ['--limpiar' => true])
        ->expectsOutputToContain('kevin+coord@ejemplo.com actuó sobre datos reales')
        ->assertSuccessful();

    expect($real->fresh())->not->toBeNull()
        ->and($coord->fresh()->deshabilitado_at)->not->toBeNull()
        ->and(User::query()->where('codigo_institucional', 'like', 'DEMO-%')->count())->toBe(1);

    // Prepararla otra vez la vuelve a habilitar.
    solicitudesDemo();
    expect($coord->fresh()->deshabilitado_at)->toBeNull();
});
