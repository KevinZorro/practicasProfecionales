<?php

declare(strict_types=1);

use App\Enums\AjusteDelLaboratorio;
use App\Enums\EstadoSolicitud;
use App\Enums\OrigenSolicitud;
use App\Enums\TipoSesion;
use App\Exceptions\CapacidadDeEstudiantesExcedida;
use App\Exceptions\SolicitudInvalida;
use App\Filament\Pages\AjustesDelLaboratorio;
use App\Livewire\Solicitud\FormatoIntramural;
use App\Livewire\Solicitud\SesionesApartadas;
use App\Mail\AvisoFormatoIntramuralMail;
use App\Models\CasoClinico;
use App\Models\ItemInventario;
use App\Models\Materia;
use App\Models\Sala;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\AjustesService;
use App\Services\DatosSesionApartada;
use App\Services\RegistroPrevioService;
use App\Services\SolicitudService;
use Database\Seeders\RolSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/*
 * Sesiones apartadas antes del semestre (RF57), cruces (RF58), formato
 * intramural (RF59) y aviso de las que no lo tienen (RF60).
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->servicio = app(RegistroPrevioService::class);
    $this->administrativo = User::factory()->administrativo()->create();
    $this->docente = User::factory()->docente()->create(['nombre' => 'Docente Titular']);
});

function apartada(User $docente, array $cambios = []): DatosSesionApartada
{
    return new DatosSesionApartada(...[
        'docenteId' => $docente->id,
        'materiaId' => Materia::factory()->create()->id,
        'casoClinicoId' => CasoClinico::factory()->create()->id,
        'tipo' => TipoSesion::Practica,
        'fecha' => now()->addDays(2)->format('Y-m-d'),
        'horaInicio' => '07:00',
        'horaFin' => '09:00',
        'cantidadEstudiantes' => 12,
        ...$cambios,
    ]);
}

// ---------------------------------------------------------------------
// Registro (RF57)
// ---------------------------------------------------------------------

it('registra la sesión aprobada, con su preparación y sin formato intramural', function (): void {
    $sesion = $this->servicio->registrar(apartada($this->docente, ['grupo' => 'b']), $this->administrativo);

    expect($sesion->estado)->toBe(EstadoSolicitud::Aprobada)
        ->and($sesion->origen)->toBe(OrigenSolicitud::RegistroPrevio)
        ->and($sesion->registrada_por)->toBe($this->administrativo->id)
        ->and($sesion->revisada_por)->toBeNull()
        ->and($sesion->grupo)->toBe('B')
        ->and($sesion->cantidad_estudiantes)->toBe(12)
        ->and($sesion->tieneFormatoIntramural())->toBeFalse()
        ->and($sesion->preparacion)->not->toBeNull()
        ->and($sesion->preparacion->items()->count())->toBe(0);
});

it('deja el grupo vacío si el formato físico no lo trae', function (): void {
    expect($this->servicio->registrar(apartada($this->docente), $this->administrativo)->grupo)->toBeNull();
});

it('valida la capacidad del escenario al registrar (RF74)', function (): void {
    $caso = CasoClinico::factory()->conCapacidad(7)->create();

    expect(fn () => $this->servicio->registrar(apartada($this->docente, ['casoClinicoId' => $caso->id]), $this->administrativo))
        ->toThrow(CapacidadDeEstudiantesExcedida::class);
});

it('no registra a nombre de quien no es docente', function (): void {
    expect(fn () => $this->servicio->registrar(apartada(User::factory()->estudiante()->create()), $this->administrativo))
        ->toThrow(SolicitudInvalida::class, 'a nombre de un docente');
});

it('reserva el registro a administrativos y coordinación', function (): void {
    expect(fn () => $this->servicio->registrar(apartada($this->docente), $this->docente))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => $this->servicio->registrar(apartada($this->docente), User::factory()->admin()->create()))
        ->toThrow(AuthorizationException::class);

    expect($this->servicio->registrar(apartada($this->docente), User::factory()->coordinador()->create())->exists)->toBeTrue();
});

it('marca con formato intramural la solicitud que hace el docente', function (): void {
    $solicitud = app(SolicitudService::class)->crear($this->docente, sesionCon(idsDeEstudiantes(1)));

    expect($solicitud->origen)->toBe(OrigenSolicitud::Docente)
        ->and($solicitud->tieneFormatoIntramural())->toBeTrue();
});

it('conserva la cantidad registrada hasta que el docente pone la lista', function (): void {
    $sesion = $this->servicio->registrar(apartada($this->docente), $this->administrativo);

    app(SolicitudService::class)->agregarEstudiantes($sesion, idsDeEstudiantes(2), $this->docente);

    expect($sesion->fresh()->cantidad_estudiantes)->toBe(2);
});

// ---------------------------------------------------------------------
// Cruces (RF58)
// ---------------------------------------------------------------------

it('avisa de cruces en la franja, del docente ocupado y de que no quedaría sala', function (): void {
    Sala::factory()->create();
    $existente = $this->servicio->registrar(apartada($this->docente), $this->administrativo);
    $fecha = $existente->fecha->format('Y-m-d');

    $avisos = $this->servicio->advertencias($fecha, '08:00', '10:00', $this->docente->id);

    expect($avisos)->toHaveCount(3)
        ->and($avisos[0])->toBe('El docente ya tiene otra sesión en esa franja.')
        ->and($avisos[1])->toContain('Docente Titular')
        ->and($avisos[2])->toContain('No quedaría ninguna sala libre');
});

it('no avisa nada en una franja libre ni en una contigua', function (): void {
    Sala::factory()->count(2)->create();
    $existente = $this->servicio->registrar(apartada($this->docente), $this->administrativo);

    expect($this->servicio->advertencias($existente->fecha->format('Y-m-d'), '09:00', '11:00', $this->docente->id))->toBe([]);
});

it('registra desde la pantalla y enseña los cruces mientras se llena', function (): void {
    $existente = $this->servicio->registrar(apartada($this->docente), $this->administrativo);
    $caso = CasoClinico::factory()->create(['nombre' => 'Atención de parto']);

    Livewire::actingAs($this->administrativo)
        ->test(SesionesApartadas::class)
        ->set('docenteId', $this->docente->id)
        ->set('materiaId', Materia::factory()->create()->id)
        ->set('casoClinicoId', $caso->id)
        ->set('cantidadEstudiantes', 8)
        ->set('fecha', $existente->fecha->format('Y-m-d'))
        ->set('horaInicio', '08:00')
        ->set('horaFin', '10:00')
        ->assertSee('El docente ya tiene otra sesión en esa franja.')
        ->call('registrar')
        ->assertHasNoErrors()
        ->assertSee('Atención de parto')
        ->assertSee('Sin formato intramural');

    expect(Solicitud::query()->apartadas()->count())->toBe(2);
});

it('no deja entrar a un docente a la pantalla de sesiones apartadas', function (): void {
    $this->actingAs($this->docente)->get(route('panel.sesiones-apartadas'))->assertForbidden();
});

// ---------------------------------------------------------------------
// Formato intramural (RF59)
// ---------------------------------------------------------------------

it('precarga el inventario del escenario y guarda el formato en la sesión y su preparación', function (): void {
    $caso = CasoClinico::factory()->create();
    $maniqui = ItemInventario::factory()->simulador()->create(['cantidad_total' => 5, 'cantidad_operativa' => 5]);
    $gasas = ItemInventario::factory()->equipoBasico()->create(['cantidad_total' => 50, 'cantidad_operativa' => 50]);
    $caso->items()->attach($maniqui->id, ['cantidad' => 1]);
    $sesion = $this->servicio->registrar(apartada($this->docente, ['casoClinicoId' => $caso->id]), $this->administrativo);

    Livewire::actingAs($this->administrativo)
        ->test(FormatoIntramural::class, ['solicitud' => $sesion])
        ->assertSet('items', [$maniqui->id => 1])
        ->set('itemAAgregar', $gasas->id)
        ->call('agregarItem')
        ->set("items.{$gasas->id}", 10)
        ->call('guardar')
        ->assertHasNoErrors()
        ->assertSet('faltantes', []);

    $sesion->refresh();

    expect($sesion->tieneFormatoIntramural())->toBeTrue()
        ->and($sesion->formato_intramural_por)->toBe($this->administrativo->id)
        ->and($sesion->items()->pluck('solicitud_item.cantidad', 'items_inventario.id')->all())->toEqual([$maniqui->id => 1, $gasas->id => 10])
        ->and($sesion->preparacion->items()->count())->toBe(2);
});

it('avisa de lo que no alcanza en la franja, sin impedir guardar (RF58)', function (): void {
    $maniqui = ItemInventario::factory()->simulador()->create(['cantidad_total' => 1, 'cantidad_operativa' => 1]);
    $sesion = $this->servicio->registrar(apartada($this->docente), $this->administrativo);

    $faltantes = $this->servicio->registrarFormatoIntramural($sesion, [$maniqui->id => 3], $this->administrativo);

    expect($faltantes)->toBe([$maniqui->id => 1])
        ->and($sesion->fresh()->tieneFormatoIntramural())->toBeTrue();
});

it('no guarda un formato intramural vacío ni lo deja registrar a un docente', function (): void {
    $sesion = $this->servicio->registrar(apartada($this->docente), $this->administrativo);

    expect(fn () => $this->servicio->registrarFormatoIntramural($sesion, [], $this->administrativo))
        ->toThrow(SolicitudInvalida::class, 'al menos un')
        ->and(fn () => $this->servicio->registrarFormatoIntramural($sesion, [ItemInventario::factory()->create()->id => 1], $this->docente))
        ->toThrow(AuthorizationException::class);
});

// ---------------------------------------------------------------------
// Aviso (RF60)
// ---------------------------------------------------------------------

it('avisa con un correo por docente y uno por administrativo, con todas sus sesiones', function (): void {
    Mail::fake();
    $otroDocente = User::factory()->docente()->create();
    User::factory()->administrativo()->inactivo()->create();
    $this->servicio->registrar(apartada($this->docente), $this->administrativo);
    $this->servicio->registrar(apartada($this->docente, ['horaInicio' => '10:00', 'horaFin' => '12:00']), $this->administrativo);
    $this->servicio->registrar(apartada($otroDocente), $this->administrativo);
    // Fuera de la antelación: no entra.
    $this->servicio->registrar(apartada($this->docente, ['fecha' => now()->addDays(10)->format('Y-m-d')]), $this->administrativo);

    $enviados = $this->servicio->avisarSinFormatoIntramural();

    // Dos docentes y un administrativo activo.
    expect($enviados)->toBe(3);
    Mail::assertQueued(AvisoFormatoIntramuralMail::class, 3);
    Mail::assertQueued(AvisoFormatoIntramuralMail::class, fn (AvisoFormatoIntramuralMail $correo): bool => $correo->hasTo($this->docente->email) && $correo->sesiones->count() === 2);
    Mail::assertQueued(AvisoFormatoIntramuralMail::class, fn (AvisoFormatoIntramuralMail $correo): bool => $correo->hasTo($this->administrativo->email) && $correo->sesiones->count() === 3);
});

it('no avisa de las sesiones que ya tienen formato intramural', function (): void {
    Mail::fake();
    $sesion = $this->servicio->registrar(apartada($this->docente), $this->administrativo);
    $this->servicio->registrarFormatoIntramural($sesion, [ItemInventario::factory()->create()->id => 1], $this->administrativo);

    expect($this->servicio->avisarSinFormatoIntramural())->toBe(0);
    Mail::assertNothingQueued();
});

it('usa la antelación que fije el ADMIN', function (): void {
    $this->servicio->registrar(apartada($this->docente, ['fecha' => now()->addDays(5)->format('Y-m-d')]), $this->administrativo);

    expect($this->servicio->proximasSinFormatoIntramural())->toHaveCount(0);

    app(AjustesService::class)->guardar([AjusteDelLaboratorio::DiasDeAvisoIntramural->value => 7], User::factory()->admin()->create());

    expect($this->servicio->proximasSinFormatoIntramural())->toHaveCount(1);
});

it('programa el aviso diario', function (): void {
    $eventos = collect(app(Schedule::class)->events())
        ->filter(static fn ($evento): bool => str_contains((string) $evento->command, 'sesiones:avisar-formato-intramural'));

    expect($eventos)->toHaveCount(1)
        ->and($eventos->first()->expression)->toBe('0 6 * * *');

    $this->artisan('sesiones:avisar-formato-intramural')->assertSuccessful();
});

it('deja al ADMIN cambiar la antelación desde su panel, y a nadie más', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test(AjustesDelLaboratorio::class)
        ->assertSet('datos.'.AjusteDelLaboratorio::DiasDeAvisoIntramural->value, 3)
        ->set('datos.'.AjusteDelLaboratorio::DiasDeAvisoIntramural->value, 5)
        ->call('guardar')
        ->assertHasNoErrors();

    expect(app(AjustesService::class)->diasDeAvisoIntramural())->toBe(5);

    $this->actingAs(User::factory()->coordinador()->create());
    expect(AjustesDelLaboratorio::canAccess())->toBeFalse()
        ->and(fn () => app(AjustesService::class)->guardar([AjusteDelLaboratorio::DiasDeAvisoIntramural->value => 9], User::factory()->coordinador()->create()))
        ->toThrow(AuthorizationException::class);
});
