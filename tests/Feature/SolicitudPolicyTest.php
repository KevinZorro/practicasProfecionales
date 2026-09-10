<?php

declare(strict_types=1);

use App\Enums\Rol;
use App\Models\Solicitud;
use App\Models\User;
use Database\Seeders\RolSeeder;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->solicitud = Solicitud::factory()->create();
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

    expect($docente->can('aprobar', $this->solicitud))->toBeFalse()
        ->and($docente->can('rechazar', $this->solicitud))->toBeFalse();
});

it('deja al administrativo revisar pero no resolver', function (): void {
    $administrativo = usuarioCon(Rol::Administrativo);

    expect($administrativo->can('revisar', $this->solicitud))->toBeTrue()
        ->and($administrativo->can('aprobar', $this->solicitud))->toBeFalse()
        ->and($administrativo->can('rechazar', $this->solicitud))->toBeFalse();
});

it('deja al coordinador revisar y también resolver', function (): void {
    // El coordinador hereda lo del administrativo, así que revisar le
    // corresponde igual que resolver.
    $coordinadora = usuarioCon(Rol::Coordinador);

    expect($coordinadora->can('revisar', $this->solicitud))->toBeTrue()
        ->and($coordinadora->can('aprobar', $this->solicitud))->toBeTrue()
        ->and($coordinadora->can('rechazar', $this->solicitud))->toBeTrue();
});

it('no deja al ADMIN meterse en el flujo de solicitudes', function (): void {
    // El §6.1 no le marca ninguna de estas tres acciones.
    $admin = usuarioCon(Rol::Admin);

    expect($admin->can('revisar', $this->solicitud))->toBeFalse()
        ->and($admin->can('aprobar', $this->solicitud))->toBeFalse()
        ->and($admin->can('rechazar', $this->solicitud))->toBeFalse();
});

it('deja a un docente ver sus solicitudes pero no las ajenas', function (): void {
    $docente = usuarioCon(Rol::Docente);
    $suya = Solicitud::factory()->create(['docente_id' => $docente->id]);
    $ajena = Solicitud::factory()->create();

    expect($docente->can('view', $suya))->toBeTrue()
        ->and($docente->can('view', $ajena))->toBeFalse();
});

it('deja ver cualquier solicitud a quien la revisa', function (): void {
    expect(usuarioCon(Rol::Administrativo)->can('view', $this->solicitud))->toBeTrue()
        ->and(usuarioCon(Rol::Coordinador)->can('view', $this->solicitud))->toBeTrue()
        ->and(usuarioCon(Rol::Estudiante)->can('view', $this->solicitud))->toBeFalse();
});

it('deja ver el calendario a los cinco roles', function (Rol $rol): void {
    expect(usuarioCon($rol)->can('verCalendario', Solicitud::class))->toBeTrue();
})->with(Rol::cases());

it('no deja ver el calendario a un usuario sin rol', function (): void {
    expect(User::factory()->create()->can('verCalendario', Solicitud::class))->toBeFalse();
});
