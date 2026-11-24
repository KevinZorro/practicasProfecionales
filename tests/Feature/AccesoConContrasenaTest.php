<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/*
 * Entrada con correo y contraseña (ADR 0011), que convive con la de Google.
 * La contraseña la fija quien tiene la consola: usuarios:contrasena.
 */

const CONTRASENA_DE_PRUEBA = 'una-contrasena-larga';

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    config([
        'laboratorio.acceso.con_contrasena' => true,
        'services.google.client_id' => null,
    ]);
});

function docenteConContrasena(): User
{
    $docente = User::factory()->docente()->create(['email' => 'ana.perez@ufps.edu.co']);
    $docente->forceFill(['password' => CONTRASENA_DE_PRUEBA])->save();

    return $docente;
}

function entrarCon(string $email, string $contrasena): Illuminate\Testing\TestResponse
{
    return test()->post(route('acceso.entrar'), ['email' => $email, 'contrasena' => $contrasena]);
}

it('muestra el formulario de contraseña sin el botón de Google cuando Google no está configurado', function (): void {
    $this->get(route('acceso'))
        ->assertOk()
        ->assertSee('name="contrasena"', escape: false)
        ->assertDontSee('Entrar con Google');
});

it('muestra las dos entradas cuando las dos están disponibles', function (): void {
    config(['services.google.client_id' => 'cliente-de-prueba.apps.googleusercontent.com']);

    $this->get(route('acceso'))
        ->assertOk()
        ->assertSee('name="contrasena"', escape: false)
        ->assertSee('Entrar con Google');
});

it('un invitado que pide el panel va a la pantalla de entrada', function (): void {
    $this->get(route('panel.inicio'))->assertRedirect(route('acceso'));
});

it('entra con el correo y la contraseña correctos, sin distinguir mayúsculas en el correo', function (): void {
    $docente = docenteConContrasena();

    entrarCon('  Ana.Perez@UFPS.edu.co ', CONTRASENA_DE_PRUEBA)->assertRedirect(route('panel.inicio'));

    expect(Auth::id())->toBe($docente->id);
});

it('vuelve a la página que pidió antes de entrar', function (): void {
    docenteConContrasena();
    $this->get(route('panel.mis-solicitudes'));

    entrarCon('ana.perez@ufps.edu.co', CONTRASENA_DE_PRUEBA)->assertRedirect(route('panel.mis-solicitudes'));
});

it('guarda la contraseña cifrada, nunca tal cual', function (): void {
    $docente = docenteConContrasena();

    expect($docente->fresh()->password)->not->toBe(CONTRASENA_DE_PRUEBA)
        ->and(Hash::check(CONTRASENA_DE_PRUEBA, (string) $docente->fresh()->password))->toBeTrue()
        ->and($docente->fresh()->toArray())->not->toHaveKey('password');
});

it('da el mismo mensaje con la contraseña equivocada, con un correo sin cuenta y con una cuenta sin contraseña', function (string $email, string $contrasena): void {
    docenteConContrasena();
    User::factory()->docente()->create(['email' => 'sin.contrasena@ufps.edu.co']);

    entrarCon($email, $contrasena)
        ->assertRedirect(route('acceso'))
        ->assertSessionHasErrors(['acceso' => 'El correo o la contraseña no son correctos.']);

    expect(Auth::check())->toBeFalse();
})->with([
    'contraseña equivocada' => ['ana.perez@ufps.edu.co', 'otra-contrasena-larga'],
    'correo sin cuenta' => ['nadie@ufps.edu.co', CONTRASENA_DE_PRUEBA],
    'cuenta sin contraseña' => ['sin.contrasena@ufps.edu.co', CONTRASENA_DE_PRUEBA],
]);

it('no deja entrar a una cuenta inactiva ni a una deshabilitada, aunque la contraseña sea correcta (regla 8)', function (string $estado): void {
    $cuenta = User::factory()->docente()->{$estado}()->create(['email' => 'ana.perez@ufps.edu.co']);
    $cuenta->forceFill(['password' => CONTRASENA_DE_PRUEBA])->save();

    entrarCon('ana.perez@ufps.edu.co', CONTRASENA_DE_PRUEBA)
        ->assertRedirect(route('acceso'))
        ->assertSessionHasErrors('acceso');

    expect(Auth::check())->toBeFalse();
})->with(['inactivo', 'deshabilitado']);

it('frena a quien prueba contraseñas y deja entrar a otro correo desde la misma IP', function (): void {
    docenteConContrasena();
    $otra = User::factory()->docente()->create(['email' => 'luis.gomez@ufps.edu.co']);
    $otra->forceFill(['password' => CONTRASENA_DE_PRUEBA])->save();

    foreach (range(1, 5) as $intento) {
        entrarCon('ana.perez@ufps.edu.co', 'equivocada-'.$intento.'-larga');
    }

    entrarCon('ana.perez@ufps.edu.co', CONTRASENA_DE_PRUEBA)
        ->assertSessionHasErrors('acceso');
    expect(Auth::check())->toBeFalse();

    entrarCon('luis.gomez@ufps.edu.co', CONTRASENA_DE_PRUEBA)->assertRedirect(route('panel.inicio'));
    expect(Auth::id())->toBe($otra->id);
});

it('cambia la sesión al entrar: nada de la anterior sobrevive', function (): void {
    docenteConContrasena();
    $this->withSession(['rastro' => 'de otra persona']);

    entrarCon('ana.perez@ufps.edu.co', CONTRASENA_DE_PRUEBA);

    expect(session('rastro'))->toBeNull();
});

it('apagada, el formulario no existe y sin Google tampoco la pantalla', function (): void {
    config(['laboratorio.acceso.con_contrasena' => false]);
    docenteConContrasena();

    $this->get(route('acceso'))->assertNotFound();
    entrarCon('ana.perez@ufps.edu.co', CONTRASENA_DE_PRUEBA)->assertNotFound();
    expect(Auth::check())->toBeFalse();
});

it('apagada con Google configurado, la pantalla solo ofrece Google', function (): void {
    config([
        'laboratorio.acceso.con_contrasena' => false,
        'services.google.client_id' => 'cliente-de-prueba.apps.googleusercontent.com',
    ]);

    $this->get(route('acceso'))
        ->assertOk()
        ->assertSee('Entrar con Google')
        ->assertDontSee('name="contrasena"', escape: false);
});

describe('usuarios:contrasena', function (): void {
    it('fija la contraseña que se escribe dos veces, y con ella se entra', function (): void {
        $docente = User::factory()->docente()->create(['email' => 'ana.perez@ufps.edu.co']);

        $this->artisan('usuarios:contrasena', ['email' => 'Ana.Perez@ufps.edu.co'])
            ->expectsQuestion('Contraseña nueva', CONTRASENA_DE_PRUEBA)
            ->expectsQuestion('Repítela', CONTRASENA_DE_PRUEBA)
            ->expectsOutputToContain('Quedó la contraseña')
            ->assertSuccessful();

        entrarCon('ana.perez@ufps.edu.co', CONTRASENA_DE_PRUEBA)->assertRedirect(route('panel.inicio'));
        expect(Auth::id())->toBe($docente->id);
    });

    it('no cambia nada si las dos no coinciden', function (): void {
        $docente = User::factory()->docente()->create();

        $this->artisan('usuarios:contrasena', ['email' => $docente->email])
            ->expectsQuestion('Contraseña nueva', CONTRASENA_DE_PRUEBA)
            ->expectsQuestion('Repítela', 'otra-contrasena-larga')
            ->assertFailed();

        expect($docente->fresh()->password)->toBeNull();
    });

    it('rechaza una contraseña corta', function (): void {
        $docente = User::factory()->docente()->create();

        $this->artisan('usuarios:contrasena', ['email' => $docente->email])
            ->expectsQuestion('Contraseña nueva', 'corta')
            ->expectsQuestion('Repítela', 'corta')
            ->expectsOutputToContain('al menos 12 caracteres')
            ->assertFailed();

        expect($docente->fresh()->password)->toBeNull();
    });

    it('falla con un correo sin cuenta', function (): void {
        $this->artisan('usuarios:contrasena', ['email' => 'nadie@ufps.edu.co', '--generar' => true])
            ->expectsOutputToContain('No hay una cuenta')
            ->assertFailed();
    });

    it('con --generar inventa una y la muestra una vez', function (): void {
        $docente = User::factory()->docente()->create();

        $this->artisan('usuarios:contrasena', ['email' => $docente->email, '--generar' => true])
            ->expectsOutputToContain('Contraseña: ')
            ->assertSuccessful();

        expect($docente->fresh()->password)->not->toBeNull();
    });

    it('cierra las sesiones con «recordarme» de la cuenta', function (): void {
        $docente = User::factory()->docente()->create(['remember_token' => 'un-token-viejo']);

        $this->artisan('usuarios:contrasena', ['email' => $docente->email, '--generar' => true])->assertSuccessful();

        expect($docente->fresh()->remember_token)->toBeNull();
    });
});
