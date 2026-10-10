<?php

declare(strict_types=1);

use App\Enums\AccionAuditada;
use App\Enums\EstadoUsuario;
use App\Enums\OrigenUsuario;
use App\Exceptions\UsuarioInvalido;
use App\Livewire\Confidencialidad\EstadoDeFirmantes;
use App\Livewire\Usuario\FormularioDeCuenta;
use App\Livewire\Usuario\RolesDeUsuarios;
use App\Models\RegistroDeBitacora;
use App\Models\User;
use App\Services\AccesoService;
use App\Services\DatosUsuario;
use App\Services\UsuarioService;
use Database\Seeders\RolSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->servicio = app(UsuarioService::class);
    $this->admin = User::factory()->admin()->create();
});

// ---------------------------------------------------------------------
// Crear y editar (RF22)
// ---------------------------------------------------------------------

it('crea una cuenta manual, activa y con el correo en minúscula', function (): void {
    $cuenta = $this->servicio->crear(new DatosUsuario(
        nombre: '  Pau Pasante ',
        email: 'Pau.Pasante@Ejemplo.edu.co',
        documento: '',
        programa: 'Enfermería',
    ), $this->admin);

    expect($cuenta->nombre)->toBe('Pau Pasante')
        ->and($cuenta->email)->toBe('pau.pasante@ejemplo.edu.co')
        ->and($cuenta->documento)->toBeNull()
        ->and($cuenta->programa)->toBe('Enfermería')
        ->and($cuenta->origen)->toBe(OrigenUsuario::Manual)
        ->and($cuenta->estado)->toBe(EstadoUsuario::Activo)
        ->and($cuenta->roles)->toBeEmpty();
});

it('no crea una cuenta con un correo que ya existe, sin distinguir mayúsculas', function (): void {
    User::factory()->create(['email' => 'ocupado@ejemplo.edu.co']);

    $this->servicio->crear(new DatosUsuario('Otra persona', 'OCUPADO@ejemplo.edu.co'), $this->admin);
})->throws(UsuarioInvalido::class);

it('deja guardar una cuenta con su propio correo', function (): void {
    $cuenta = User::factory()->manual()->create(['email' => 'mismo@ejemplo.edu.co']);

    $this->servicio->actualizar($cuenta, new DatosUsuario('Nombre corregido', 'mismo@ejemplo.edu.co'), $this->admin);

    expect($cuenta->fresh()->nombre)->toBe('Nombre corregido');
});

it('no deja crear ni editar cuentas a quien no es ADMIN', function (string $estado): void {
    $coordinadora = User::factory()->{$estado}()->create();

    expect(fn () => $this->servicio->crear(new DatosUsuario('X', 'x@ejemplo.edu.co'), $coordinadora))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => $this->servicio->actualizar($this->admin, new DatosUsuario('X', 'x@ejemplo.edu.co'), $coordinadora))
        ->toThrow(AuthorizationException::class);
})->with(['coordinador', 'administrativo', 'docente']);

it('crea la cuenta desde el formulario y vuelve a la lista', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(FormularioDeCuenta::class)
        ->set('nombre', 'Ingeniero Externo')
        ->set('correo', 'ingeniero@ejemplo.edu.co')
        ->call('guardar')
        ->assertHasNoErrors()
        ->assertRedirect(route('panel.usuarios'));

    expect(User::where('email', 'ingeniero@ejemplo.edu.co')->value('origen'))->toBe(OrigenUsuario::Manual);
});

it('explica en el formulario que el correo ya está ocupado', function (): void {
    User::factory()->create(['email' => 'ocupado@ejemplo.edu.co']);
    $this->actingAs($this->admin);

    Livewire::test(FormularioDeCuenta::class)
        ->set('nombre', 'Otra persona')
        ->set('correo', 'ocupado@ejemplo.edu.co')
        ->call('guardar')
        ->assertSet('errorDeRegla', UsuarioInvalido::correoRepetido('ocupado@ejemplo.edu.co')->getMessage());
});

it('conserva el programa que ya tenía la cuenta aunque no esté en la lista', function (): void {
    $cuenta = User::factory()->create(['programa' => 'Medicina']);
    $this->actingAs($this->admin);

    Livewire::test(FormularioDeCuenta::class, ['cuenta' => $cuenta])
        ->assertSee('Medicina')
        ->call('guardar')
        ->assertHasNoErrors();

    expect($cuenta->fresh()->programa)->toBe('Medicina');
});

it('solo abre el alta y la edición de cuentas al ADMIN', function (): void {
    $cuenta = User::factory()->create();
    $coordinadora = User::factory()->coordinador()->create();

    $this->actingAs($this->admin)->get(route('panel.usuarios.nueva'))->assertOk();
    $this->actingAs($this->admin)->get(route('panel.usuarios.editar', $cuenta))->assertOk()->assertSee($cuenta->email);

    $this->actingAs($coordinadora)->get(route('panel.usuarios.nueva'))->assertForbidden();
    $this->actingAs($coordinadora)->get(route('panel.usuarios.editar', $cuenta))->assertForbidden();
});

// ---------------------------------------------------------------------
// Deshabilitar y habilitar (RF22, D6)
// ---------------------------------------------------------------------

it('deshabilita una cuenta con motivo, sin tocar su vigencia institucional, y lo deja en la bitácora', function (): void {
    $docente = User::factory()->docente()->create();

    $this->servicio->deshabilitar($docente, 'Uso indebido de la cuenta.', $this->admin);

    $docente->refresh();
    expect($docente->estaDeshabilitado())->toBeTrue()
        ->and($docente->estado)->toBe(EstadoUsuario::Activo)
        ->and($docente->deshabilitado_por)->toBe($this->admin->id)
        ->and($docente->motivo_deshabilitacion)->toBe('Uso indebido de la cuenta.')
        ->and(app(AccesoService::class)->puedeEntrar($docente))->toBeFalse()
        ->and(RegistroDeBitacora::where('accion', AccionAuditada::UsuarioDeshabilitado)->count())->toBe(1);
});

it('vuelve a habilitar la cuenta y borra la marca', function (): void {
    $docente = User::factory()->docente()->deshabilitado()->create();

    $this->servicio->habilitar($docente, 'Se aclaró el caso.', $this->admin);

    $docente->refresh();
    expect($docente->estaDeshabilitado())->toBeFalse()
        ->and($docente->motivo_deshabilitacion)->toBeNull()
        ->and(app(AccesoService::class)->puedeEntrar($docente))->toBeTrue()
        ->and(RegistroDeBitacora::where('accion', AccionAuditada::UsuarioHabilitado)->count())->toBe(1);
});

it('no deja deshabilitar sin motivo', function (): void {
    $this->servicio->deshabilitar(User::factory()->create(), '   ', $this->admin);
})->throws(UsuarioInvalido::class);

it('no deja que el ADMIN se deshabilite a sí mismo', function (): void {
    $this->servicio->deshabilitar($this->admin, 'Por probar.', $this->admin);
})->throws(UsuarioInvalido::class);

it('no deja deshabilitar dos veces la misma cuenta', function (): void {
    $this->servicio->deshabilitar(User::factory()->deshabilitado()->create(), 'Otra vez.', $this->admin);
})->throws(UsuarioInvalido::class);

it('no deja deshabilitar a quien no es ADMIN', function (): void {
    $coordinadora = User::factory()->coordinador()->create();

    $this->servicio->deshabilitar(User::factory()->create(), 'Motivo.', $coordinadora);
})->throws(AuthorizationException::class);

it('saca del panel a una cuenta deshabilitada con su propio mensaje', function (): void {
    $docente = User::factory()->docente()->create();
    $this->actingAs($docente)->get(route('panel.calendario'))->assertOk();

    $this->servicio->deshabilitar($docente, 'Uso indebido de la cuenta.', $this->admin);

    $this->get(route('panel.calendario'))
        ->assertForbidden()
        ->assertSee('Tu cuenta está deshabilitada');
    $this->assertGuest();
});

it('no lista a una cuenta deshabilitada entre los estudiantes que pueden ir a una sesión', function (): void {
    $habilitada = User::factory()->estudiante()->create();
    $deshabilitada = User::factory()->estudiante()->deshabilitado()->create();

    $ids = User::query()->activos()->pluck('id');

    expect($ids)->toContain($habilitada->id)->not->toContain($deshabilitada->id);
});

it('deshabilita y habilita desde la lista de usuarios', function (): void {
    $docente = User::factory()->docente()->create();
    $this->actingAs($this->admin);

    $pantalla = Livewire::test(RolesDeUsuarios::class)
        ->call('abrirAcceso', $docente->id)
        ->call('deshabilitar')
        ->assertHasErrors(['motivoDeAcceso' => 'required'])
        ->set('motivoDeAcceso', 'Préstamo de la cuenta.')
        ->call('deshabilitar')
        ->assertHasNoErrors()
        ->assertSee('Deshabilitada el');

    expect($docente->fresh()->estaDeshabilitado())->toBeTrue();

    $pantalla->call('abrirAcceso', $docente->id)
        ->set('motivoDeAcceso', 'Se aclaró.')
        ->call('habilitar');

    expect($docente->fresh()->estaDeshabilitado())->toBeFalse();
});

it('no deja que el ADMIN se deshabilite desde la lista', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(RolesDeUsuarios::class)
        ->call('abrirAcceso', $this->admin->id)
        ->set('motivoDeAcceso', 'Por probar.')
        ->call('deshabilitar')
        ->assertSet('errorDeRegla', UsuarioInvalido::noSeDeshabilitaASiMismo()->getMessage());

    expect($this->admin->fresh()->estaDeshabilitado())->toBeFalse();
});

it('enseña en la lista la vigencia institucional perdida, el origen y el programa', function (): void {
    User::factory()->estudiante()->inactivo()->create(['nombre' => 'Egresada Vieja', 'programa' => 'Regencia en Farmacia']);
    $this->actingAs($this->admin);

    Livewire::test(RolesDeUsuarios::class)
        ->assertSee('Sin vigencia institucional')
        ->assertSee('Matriculado · Regencia en Farmacia')
        ->assertSee('Nueva cuenta');
});

// ---------------------------------------------------------------------
// Programa en el estado del formato (RF53)
// ---------------------------------------------------------------------

it('filtra el estado del formato de confidencialidad por programa', function (): void {
    abrirPeriodo();
    User::factory()->estudiante()->create(['nombre' => 'Ana Enfermera', 'programa' => 'Enfermería']);
    User::factory()->estudiante()->create(['nombre' => 'Rita Regente', 'programa' => 'Regencia en Farmacia']);
    $this->actingAs(User::factory()->administrativo()->create());

    Livewire::test(EstadoDeFirmantes::class)
        ->assertSee('Ana Enfermera')
        ->assertSee('Rita Regente')
        ->set('programa', 'Regencia en Farmacia')
        ->assertDontSee('Ana Enfermera')
        ->assertSee('Rita Regente');
});
