<?php

declare(strict_types=1);

use App\Mail\RecuperarContrasenaMail;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

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

function entrarCon(string $email, string $contrasena): TestResponse
{
    return test()->post(route('acceso.entrar'), ['email' => $email, 'contrasena' => $contrasena]);
}

/** El enlace del único correo que se mandó, para abrirlo como lo haría la persona. */
function enlaceDelCorreo(): string
{
    $enlace = null;
    Mail::assertQueued(RecuperarContrasenaMail::class, function (RecuperarContrasenaMail $correo) use (&$enlace): bool {
        $enlace = $correo->enlace;

        return true;
    });

    return (string) $enlace;
}

function tokenDelEnlace(string $enlace): string
{
    return basename((string) parse_url($enlace, PHP_URL_PATH));
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

describe('recuperar la contraseña por correo', function (): void {
    beforeEach(function (): void {
        Mail::fake();
    });

    it('manda un solo correo con el enlace y con él se elige una contraseña para entrar', function (): void {
        $docente = User::factory()->docente()->create(['email' => 'ana.perez@ufps.edu.co']);

        $this->post(route('acceso.recuperar.enviar'), ['email' => 'Ana.Perez@ufps.edu.co'])
            ->assertSessionHas('estado');

        Mail::assertQueued(RecuperarContrasenaMail::class, 1);
        Mail::assertQueued(RecuperarContrasenaMail::class, fn (RecuperarContrasenaMail $correo): bool => $correo->hasTo('ana.perez@ufps.edu.co'));

        $enlace = enlaceDelCorreo();
        $this->get($enlace)->assertOk()->assertSee('Contraseña nueva');

        $this->post(route('acceso.restablecer.guardar'), [
            'token' => tokenDelEnlace($enlace),
            'email' => 'ana.perez@ufps.edu.co',
            'contrasena' => CONTRASENA_DE_PRUEBA,
            'contrasena_confirmation' => CONTRASENA_DE_PRUEBA,
        ])->assertRedirect(route('acceso'))->assertSessionHas('estado');

        entrarCon('ana.perez@ufps.edu.co', CONTRASENA_DE_PRUEBA)->assertRedirect(route('panel.inicio'));
        expect(Auth::id())->toBe($docente->id);
    });

    it('responde lo mismo con un correo sin cuenta, y no manda nada', function (): void {
        $this->post(route('acceso.recuperar.enviar'), ['email' => 'nadie@ufps.edu.co'])
            ->assertSessionHas('estado');

        Mail::assertNothingQueued();
    });

    it('no manda el enlace a una cuenta inactiva ni a una deshabilitada (regla 8)', function (string $estado): void {
        $cuenta = User::factory()->docente()->{$estado}()->create();

        $this->post(route('acceso.recuperar.enviar'), ['email' => $cuenta->email])->assertSessionHas('estado');

        Mail::assertNothingQueued();
    })->with(['inactivo', 'deshabilitado']);

    it('pedirlo dos veces seguidas manda un solo correo', function (): void {
        $docente = User::factory()->docente()->create();

        $this->post(route('acceso.recuperar.enviar'), ['email' => $docente->email]);
        $this->post(route('acceso.recuperar.enviar'), ['email' => $docente->email]);

        Mail::assertQueued(RecuperarContrasenaMail::class, 1);
    });

    it('el enlace sirve una sola vez', function (): void {
        $docente = User::factory()->docente()->create();
        $this->post(route('acceso.recuperar.enviar'), ['email' => $docente->email]);
        $datos = [
            'token' => tokenDelEnlace(enlaceDelCorreo()),
            'email' => $docente->email,
            'contrasena' => CONTRASENA_DE_PRUEBA,
            'contrasena_confirmation' => CONTRASENA_DE_PRUEBA,
        ];

        $this->post(route('acceso.restablecer.guardar'), $datos)->assertRedirect(route('acceso'));
        $this->post(route('acceso.restablecer.guardar'), [...$datos, 'contrasena' => 'otra-contrasena-larga', 'contrasena_confirmation' => 'otra-contrasena-larga'])
            ->assertSessionHasErrors('acceso');

        expect(Hash::check(CONTRASENA_DE_PRUEBA, (string) $docente->fresh()->password))->toBeTrue();
    });

    it('el enlace vence a los 60 minutos', function (): void {
        $docente = User::factory()->docente()->create();
        $this->post(route('acceso.recuperar.enviar'), ['email' => $docente->email]);
        $token = tokenDelEnlace(enlaceDelCorreo());

        $this->travel(61)->minutes();

        $this->post(route('acceso.restablecer.guardar'), [
            'token' => $token,
            'email' => $docente->email,
            'contrasena' => CONTRASENA_DE_PRUEBA,
            'contrasena_confirmation' => CONTRASENA_DE_PRUEBA,
        ])->assertSessionHasErrors('acceso');

        expect($docente->fresh()->password)->toBeNull();
    });

    it('un token inventado o de otra cuenta no sirve', function (): void {
        $ana = User::factory()->docente()->create();
        $luis = User::factory()->docente()->create();
        $this->post(route('acceso.recuperar.enviar'), ['email' => $ana->email]);

        $this->post(route('acceso.restablecer.guardar'), [
            'token' => tokenDelEnlace(enlaceDelCorreo()),
            'email' => $luis->email,
            'contrasena' => CONTRASENA_DE_PRUEBA,
            'contrasena_confirmation' => CONTRASENA_DE_PRUEBA,
        ])->assertSessionHasErrors('acceso');

        expect($luis->fresh()->password)->toBeNull();
    });

    it('exige la contraseña nueva dos veces y con el largo mínimo', function (array $contrasenas): void {
        $docente = User::factory()->docente()->create();
        $this->post(route('acceso.recuperar.enviar'), ['email' => $docente->email]);

        $this->post(route('acceso.restablecer.guardar'), [
            'token' => tokenDelEnlace(enlaceDelCorreo()),
            'email' => $docente->email,
            ...$contrasenas,
        ])->assertSessionHasErrors('contrasena');

        expect($docente->fresh()->password)->toBeNull();
    })->with([
        'no coinciden' => [['contrasena' => CONTRASENA_DE_PRUEBA, 'contrasena_confirmation' => 'otra-contrasena-larga']],
        'corta' => [['contrasena' => 'corta', 'contrasena_confirmation' => 'corta']],
    ]);

    it('no existe con la entrada con contraseña apagada', function (): void {
        config(['laboratorio.acceso.con_contrasena' => false]);

        $this->get(route('acceso.recuperar'))->assertNotFound();
        $this->post(route('acceso.recuperar.enviar'), ['email' => 'ana.perez@ufps.edu.co'])->assertNotFound();
        Mail::assertNothingQueued();
    });

    it('quien ya entró vuelve a su panel', function (): void {
        $this->actingAs(User::factory()->docente()->create())
            ->get(route('acceso.recuperar'))
            ->assertRedirect(route('panel.inicio'));
    });
});
