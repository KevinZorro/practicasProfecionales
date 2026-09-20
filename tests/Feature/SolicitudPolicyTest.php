<?php

declare(strict_types=1);

use App\Enums\Rol;
use App\Models\Solicitud;
use App\Models\User;
use Database\Seeders\RolSeeder;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    // Sin revisar todavía: es el estado en el que nace toda solicitud.
    $this->solicitud = Solicitud::factory()->create();
    $this->revisada = Solicitud::factory()->revisada()->create();
});

/** Usuario con un solo rol. */
function usuarioCon(Rol $rol): User
{
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);

    return $usuario;
}

it('deja solicitar un escenario solo al docente', function (): void {
    expect(usuarioCon(Rol::Docente)->can('create', Solicitud::class))->toBeTrue()
        ->and(usuarioCon(Rol::Administrativo)->can('create', Solicitud::class))->toBeFalse()
        ->and(usuarioCon(Rol::Coordinador)->can('create', Solicitud::class))->toBeFalse()
        ->and(usuarioCon(Rol::Admin)->can('create', Solicitud::class))->toBeFalse()
        ->and(usuarioCon(Rol::Estudiante)->can('create', Solicitud::class))->toBeFalse();
});

it('no deja a un docente aprobar ni rechazar', function (): void {
    $docente = usuarioCon(Rol::Docente);

    expect($docente->can('aprobar', $this->revisada))->toBeFalse()
        ->and($docente->can('rechazar', $this->revisada))->toBeFalse();
});

it('deja al administrativo revisar pero no resolver', function (): void {
    $administrativo = usuarioCon(Rol::Administrativo);

    expect($administrativo->can('revisar', $this->solicitud))->toBeTrue()
        ->and($administrativo->can('aprobar', $this->revisada))->toBeFalse()
        ->and($administrativo->can('rechazar', $this->revisada))->toBeFalse();
});

it('deja al coordinador revisar y también resolver una solicitud revisada', function (): void {
    // El coordinador hereda lo del administrativo, así que revisar le
    // corresponde igual que resolver.
    $coordinadora = usuarioCon(Rol::Coordinador);

    expect($coordinadora->can('revisar', $this->solicitud))->toBeTrue()
        ->and($coordinadora->can('aprobar', $this->revisada))->toBeTrue()
        ->and($coordinadora->can('rechazar', $this->revisada))->toBeTrue();
});

// ---------------------------------------------------------------------
// Aprobación: hace falta revisión administrativa previa
// ---------------------------------------------------------------------

it('deja al ADMIN aprobar una solicitud ya revisada', function (): void {
    // Aprueba cuando la coordinadora no está disponible.
    expect(usuarioCon(Rol::Admin)->can('aprobar', $this->revisada))->toBeTrue();
});

it('no deja aprobar una solicitud sin revisión previa', function (Rol $rol): void {
    // Ni el ADMIN ni la coordinadora: sin revisión administrativa registrada
    // no aprueba nadie.
    expect(usuarioCon($rol)->can('aprobar', $this->solicitud))->toBeFalse();
})->with(['admin' => Rol::Admin, 'coordinador' => Rol::Coordinador]);

it('no deja aprobar una solicitud ya resuelta', function (string $estado): void {
    $resuelta = Solicitud::factory()->$estado()->create();

    expect(usuarioCon(Rol::Coordinador)->can('aprobar', $resuelta))->toBeFalse()
        ->and(usuarioCon(Rol::Admin)->can('aprobar', $resuelta))->toBeFalse();
})->with(['aprobada', 'rechazada']);

it('no deja al ADMIN revisar ni rechazar', function (): void {
    // Revisar es del administrativo, así que quien aprueba nunca es quien
    // revisó. Rechazar sigue pendiente de confirmar con el cliente.
    $admin = usuarioCon(Rol::Admin);

    expect($admin->can('revisar', $this->solicitud))->toBeFalse()
        ->and($admin->can('rechazar', $this->revisada))->toBeFalse();
});

it('deja al ADMIN entrar a la bandeja, porque es donde aprueba', function (): void {
    expect(usuarioCon(Rol::Admin)->can('verBandeja', Solicitud::class))->toBeTrue()
        ->and(usuarioCon(Rol::Administrativo)->can('verBandeja', Solicitud::class))->toBeTrue()
        ->and(usuarioCon(Rol::Coordinador)->can('verBandeja', Solicitud::class))->toBeTrue()
        ->and(usuarioCon(Rol::Docente)->can('verBandeja', Solicitud::class))->toBeFalse()
        ->and(usuarioCon(Rol::Estudiante)->can('verBandeja', Solicitud::class))->toBeFalse();
});

it('deja a un docente ver sus solicitudes pero no las ajenas', function (): void {
    $docente = usuarioCon(Rol::Docente);
    $suya = Solicitud::factory()->create(['docente_id' => $docente->id]);
    $ajena = Solicitud::factory()->create();

    expect($docente->can('view', $suya))->toBeTrue()
        ->and($docente->can('view', $ajena))->toBeFalse();
});

it('deja ver cualquier solicitud a quien entra a la bandeja', function (): void {
    expect(usuarioCon(Rol::Administrativo)->can('view', $this->solicitud))->toBeTrue()
        ->and(usuarioCon(Rol::Coordinador)->can('view', $this->solicitud))->toBeTrue()
        ->and(usuarioCon(Rol::Admin)->can('view', $this->solicitud))->toBeTrue()
        ->and(usuarioCon(Rol::Estudiante)->can('view', $this->solicitud))->toBeFalse();
});

it('deja ver el calendario a los cinco roles', function (Rol $rol): void {
    expect(usuarioCon($rol)->can('verCalendario', Solicitud::class))->toBeTrue();
})->with(Rol::cases());

it('no deja ver el calendario a un usuario sin rol', function (): void {
    expect(User::factory()->create()->can('verCalendario', Solicitud::class))->toBeFalse();
});
