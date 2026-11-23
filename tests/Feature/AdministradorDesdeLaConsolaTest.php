<?php

declare(strict_types=1);

use App\Enums\EstadoUsuario;
use App\Enums\OrigenUsuario;
use App\Enums\Rol;
use App\Models\AsignacionDeRol;
use App\Models\RegistroDeBitacora;
use App\Models\User;
use App\Services\AsignacionDeRolService;
use Database\Seeders\RolSeeder;
use Spatie\Permission\Models\Role;

/*
 * Una instalación nueva nace sin cuentas, y la entrada con Google no crea
 * ninguna (RF18): sin el primer ADMIN nadie podría entrar a repartir roles.
 * Lo nombra quien tiene la consola del servidor, con
 * usuarios:crear-administrador, que "produccion.sh instalar" corre en cada
 * pasada.
 */

/** @return list<string> */
function nombresDeLosRoles(): array
{
    return Role::query()->orderBy('name')->pluck('name')->all();
}

function asignacionesDeAdmin(User $cuenta): int
{
    return AsignacionDeRol::query()
        ->where('user_id', $cuenta->id)
        ->where('role_id', Role::findByName(Rol::Admin->value)->id)
        ->count();
}

it('crea los seis roles del enum, y volver a correrlo en el siguiente despliegue no los duplica', function (): void {
    // RefreshDatabase solo migra: así queda la base en el servidor antes del seeder.
    expect(nombresDeLosRoles())->toBe([]);

    $this->seed(RolSeeder::class);
    $this->seed(RolSeeder::class);

    $esperados = array_map(static fn (Rol $rol): string => $rol->value, Rol::cases());
    sort($esperados);

    expect(nombresDeLosRoles())->toBe($esperados);
});

describe('con los roles creados', function (): void {
    beforeEach(function (): void {
        $this->seed(RolSeeder::class);
    });

    it('crea la primera cuenta como manual, activa y con el rol de ADMIN permanente', function (): void {
        $this->artisan('usuarios:crear-administrador', ['email' => '  Ana.Perez@Ejemplo.edu.co ', 'nombre' => 'Ana Pérez'])
            ->expectsOutputToContain('Creó la cuenta de Ana Pérez')
            ->assertSuccessful();

        $cuenta = User::query()->sole();
        $asignacion = AsignacionDeRol::query()->sole();

        expect($cuenta->email)->toBe('ana.perez@ejemplo.edu.co')
            ->and($cuenta->nombre)->toBe('Ana Pérez')
            ->and($cuenta->origen)->toBe(OrigenUsuario::Manual)
            ->and($cuenta->estado)->toBe(EstadoUsuario::Activo)
            ->and($cuenta->hasRole(Rol::Admin->value))->toBeTrue()
            ->and($asignacion->hasta)->toBeNull()
            ->and($asignacion->asignado_por)->toBeNull()
            ->and($asignacion->motivo)->toBe(AsignacionDeRolService::MOTIVO_CONSOLA);
    });

    it('deja al primer ADMIN repartir los roles desde el panel', function (): void {
        $this->artisan('usuarios:crear-administrador', ['email' => 'ana@ejemplo.edu.co', 'nombre' => 'Ana Pérez'])->assertSuccessful();
        $admin = User::query()->sole();
        $docente = User::factory()->create();

        app(AsignacionDeRolService::class)->asignar($admin, $docente, Rol::Docente);

        expect($docente->hasRole(Rol::Docente->value))->toBeTrue();
    });

    it('no escribe en la bitácora, que exige una persona que actúe, pero deja el rastro en el historial de roles', function (): void {
        $this->artisan('usuarios:crear-administrador', ['email' => 'ana@ejemplo.edu.co', 'nombre' => 'Ana Pérez'])->assertSuccessful();

        expect(RegistroDeBitacora::query()->count())->toBe(0)
            ->and(AsignacionDeRol::query()->where('motivo', AsignacionDeRolService::MOTIVO_CONSOLA)->count())->toBe(1);
    });

    it('le da el rol a una cuenta que ya existe sin tocarle los datos ni los otros roles', function (): void {
        $docente = User::factory()->docente()->create(['nombre' => 'Laura Gómez', 'email' => 'laura@ejemplo.edu.co']);

        $this->artisan('usuarios:crear-administrador', ['email' => 'LAURA@ejemplo.edu.co'])
            ->expectsOutputToContain('ya existía')
            ->assertSuccessful();

        $docente->refresh();

        expect(User::query()->count())->toBe(1)
            ->and($docente->nombre)->toBe('Laura Gómez')
            ->and($docente->origen)->toBe(OrigenUsuario::Contratado)
            ->and($docente->hasRole(Rol::Admin->value))->toBeTrue()
            ->and($docente->hasRole(Rol::Docente->value))->toBeTrue();
    });

    it('se puede correr en cada instalación: la segunda vez no cambia nada', function (): void {
        $this->artisan('usuarios:crear-administrador', ['email' => 'ana@ejemplo.edu.co', 'nombre' => 'Ana Pérez'])->assertSuccessful();

        $this->artisan('usuarios:crear-administrador', ['email' => 'ana@ejemplo.edu.co', 'nombre' => 'Otro Nombre'])
            ->expectsOutputToContain('ya era ADMIN')
            ->assertSuccessful();

        $cuenta = User::query()->sole();

        expect($cuenta->nombre)->toBe('Ana Pérez')
            ->and(asignacionesDeAdmin($cuenta))->toBe(1);
    });

    it('no crea una cuenta sin el nombre de la persona', function (): void {
        $this->artisan('usuarios:crear-administrador', ['email' => 'nadie@ejemplo.edu.co'])
            ->expectsOutputToContain('hace falta el nombre')
            ->assertFailed();

        expect(User::query()->count())->toBe(0);
    });

    it('no acepta un correo mal escrito', function (string $email): void {
        $this->artisan('usuarios:crear-administrador', ['email' => $email, 'nombre' => 'Ana Pérez'])
            ->expectsOutputToContain('no es un correo válido')
            ->assertFailed();

        expect(User::query()->count())->toBe(0);
    })->with([
        'sin arroba' => ['ana.ejemplo.edu.co'],
        'vacío' => ['   '],
    ]);

    it('avisa cuando la cuenta no podrá entrar, aunque le da el rol', function (string $estado, string $aviso): void {
        $cuenta = User::factory()->{$estado}()->create(['email' => 'laura@ejemplo.edu.co']);

        $this->artisan('usuarios:crear-administrador', ['email' => 'laura@ejemplo.edu.co'])
            ->expectsOutputToContain($aviso)
            ->assertSuccessful();

        expect($cuenta->hasRole(Rol::Admin->value))->toBeTrue();
    })->with([
        'deshabilitada por el ADMIN' => ['deshabilitado', 'la cuenta está deshabilitada'],
        'sin vinculación vigente' => ['inactivo', 'no está vigente'],
    ]);

    it('avisa si el correo no es del dominio que admite la entrada con Google', function (): void {
        config(['services.google.dominio' => 'ufps.edu.co']);

        $this->artisan('usuarios:crear-administrador', ['email' => 'ana@gmail.com', 'nombre' => 'Ana Pérez'])
            ->expectsOutputToContain('solo admite cuentas de ufps.edu.co')
            ->assertSuccessful();
    });

    it('avisa si la entrada con Google todavía no está configurada', function (): void {
        config(['services.google.client_id' => null]);

        $this->artisan('usuarios:crear-administrador', ['email' => 'ana@ejemplo.edu.co', 'nombre' => 'Ana Pérez'])
            ->expectsOutputToContain('GOOGLE_CLIENT_ID')
            ->assertSuccessful();
    });
});

it('sin los roles en la base, pide correr primero las migraciones y no deja una cuenta a medias', function (): void {
    $this->artisan('usuarios:crear-administrador', ['email' => 'ana@ejemplo.edu.co', 'nombre' => 'Ana Pérez'])
        ->expectsOutputToContain('run --rm migraciones')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});
