<?php

declare(strict_types=1);

use App\Enums\EstadoUsuario;
use App\Enums\Rol;
use App\Models\User;
use App\Services\AccesoService;
use App\Services\IdentidadDeGoogle;
use Database\Seeders\RolSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as CuentaDeGoogle;

/*
 * Entrada con la cuenta institucional de Google (RF18).
 *
 * Google se simula en el único punto donde el controlador habla con él: el
 * usuario que devuelve Socialite al volver. Todo lo demás —rutas, sesión,
 * AccesoService, la base— es de verdad. La redirección hacia Google usa el
 * proveedor real, porque no sale a la red: solo arma la URL.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    config([
        'services.google.client_id' => 'cliente-de-prueba.apps.googleusercontent.com',
        'services.google.client_secret' => 'secreto-de-prueba',
        'services.google.dominio' => 'ufps.edu.co',
    ]);
});

/**
 * Lo que Google devuelve de la persona, tal como llega del endpoint userinfo.
 *
 * @param  array<string, mixed>  $datos
 */
function googleDevuelve(array $datos = []): void
{
    $datos = [
        'sub' => '109876543210987654321',
        'email' => 'laura.perez@ufps.edu.co',
        'email_verified' => true,
        'hd' => 'ufps.edu.co',
        'name' => 'Laura Pérez',
        ...$datos,
    ];

    $cuenta = (new CuentaDeGoogle)->setRaw($datos)->map([
        'id' => $datos['sub'],
        'email' => $datos['email'],
        'name' => $datos['name'],
    ]);

    $proveedor = Mockery::mock(GoogleProvider::class);
    $proveedor->shouldReceive('user')->andReturn($cuenta);

    Socialite::shouldReceive('driver')->with('google')->andReturn($proveedor);
}

function volverDeGoogle(): TestResponse
{
    return test()->get(route('acceso.google.volver', ['code' => 'codigo-de-google', 'state' => 'estado']));
}

function docenteDeLaUfps(array $datos = []): User
{
    $usuario = User::factory()->create([
        'google_id' => null,
        'email' => 'laura.perez@ufps.edu.co',
        ...$datos,
    ]);
    $usuario->assignRole(Rol::Docente->value);

    return $usuario;
}

// ---------------------------------------------------------------------
// Sin credenciales, la entrada no existe
// ---------------------------------------------------------------------

it('no abre ninguna de las tres rutas sin credenciales de Google', function (): void {
    config(['services.google.client_id' => null]);

    $this->get(route('acceso'))->assertNotFound();
    $this->get(route('acceso.google'))->assertNotFound();
    volverDeGoogle()->assertNotFound();
});

it('trata como ausente un GOOGLE_CLIENT_ID vacío', function (): void {
    // Es lo que queda si se copia .env.example sin llenarlo.
    config(['services.google.client_id' => '']);

    $this->get(route('acceso'))->assertNotFound();
});

it('manda al invitado del panel a la entrada con Google', function (): void {
    $this->get(route('panel.inicio'))->assertRedirect(route('acceso'));
});

it('manda al invitado a la portada si no hay credenciales', function (): void {
    config(['services.google.client_id' => null]);

    $this->get(route('panel.inicio'))->assertRedirect('/');
});

// ---------------------------------------------------------------------
// La pantalla de entrada y la ida a Google
// ---------------------------------------------------------------------

it('enseña el botón de Google y el dominio institucional', function (): void {
    $this->get(route('acceso'))
        ->assertOk()
        ->assertSee('Entrar con Google')
        ->assertSee('@ufps.edu.co')
        ->assertSee(route('acceso.google'));
});

it('lleva al panel a quien ya tiene la sesión abierta', function (): void {
    $this->actingAs(docenteDeLaUfps())->get(route('acceso'))->assertRedirect(route('panel.inicio'));
});

it('manda a Google pidiendo solo cuentas del dominio y que elija cuenta', function (): void {
    $respuesta = $this->get(route('acceso.google'));

    $destino = (string) $respuesta->headers->get('Location');
    parse_str((string) parse_url($destino, PHP_URL_QUERY), $parametros);

    expect($destino)->toStartWith('https://accounts.google.com/o/oauth2/auth')
        ->and($parametros['client_id'])->toBe('cliente-de-prueba.apps.googleusercontent.com')
        ->and($parametros['redirect_uri'])->toBe(url('/acceso/google/volver'))
        ->and($parametros['hd'])->toBe('ufps.edu.co')
        ->and($parametros['prompt'])->toBe('select_account')
        ->and(explode(' ', $parametros['scope']))->toContain('openid', 'email')
        ->and($parametros['state'])->toBe(session('state'));
});

it('no manda "hd" a Google si no hay dominio configurado', function (): void {
    config(['services.google.dominio' => null]);

    $destino = (string) $this->get(route('acceso.google'))->headers->get('Location');
    parse_str((string) parse_url($destino, PHP_URL_QUERY), $parametros);

    expect($parametros)->not->toHaveKey('hd');
});

// ---------------------------------------------------------------------
// La vuelta: quién entra
// ---------------------------------------------------------------------

it('abre la sesión de quien ya había entrado con Google', function (): void {
    $usuario = docenteDeLaUfps(['google_id' => '109876543210987654321']);
    googleDevuelve();

    volverDeGoogle()->assertRedirect(route('panel.inicio'));

    expect(Auth::id())->toBe($usuario->id);
});

it('vincula la cuenta la primera vez, encontrándola por el correo', function (): void {
    $usuario = docenteDeLaUfps();
    googleDevuelve(['email' => 'Laura.Perez@UFPS.edu.co']);

    volverDeGoogle()->assertRedirect(route('panel.inicio'));

    expect(Auth::id())->toBe($usuario->id)
        ->and($usuario->fresh()->google_id)->toBe('109876543210987654321');
});

it('encuentra la cuenta por el identificador de Google aunque haya cambiado el correo', function (): void {
    $usuario = docenteDeLaUfps(['google_id' => '109876543210987654321', 'email' => 'laura.perez@ufps.edu.co']);
    googleDevuelve(['email' => 'lperez@ufps.edu.co']);

    volverDeGoogle()->assertRedirect(route('panel.inicio'));

    expect(Auth::id())->toBe($usuario->id);
});

it('no crea cuentas: un correo sin cuenta en la plataforma no entra', function (): void {
    // Regla 8: tener un correo institucional no autoriza el ingreso.
    googleDevuelve(['email' => 'desconocido@ufps.edu.co']);
    $antes = User::count();

    volverDeGoogle()
        ->assertRedirect(route('acceso'))
        ->assertSessionHasErrors(['acceso' => 'No hay una cuenta de la plataforma para ese correo. Si eres estudiante, docente o funcionario del laboratorio, comunícate con el laboratorio.']);

    expect(Auth::check())->toBeFalse()
        ->and(User::count())->toBe($antes);
});

it('no deja entrar a quien perdió la vigencia institucional', function (): void {
    // Regla 8: un egresado conserva el correo institucional.
    docenteDeLaUfps(['estado' => EstadoUsuario::Inactivo]);
    googleDevuelve();

    volverDeGoogle()
        ->assertRedirect(route('acceso'))
        ->assertSessionHasErrors(['acceso' => app(AccesoService::class)->motivoDelRechazo()]);

    expect(Auth::check())->toBeFalse();
});

it('no vincula la cuenta de quien no puede entrar', function (): void {
    $usuario = docenteDeLaUfps(['estado' => EstadoUsuario::Inactivo]);
    googleDevuelve();

    volverDeGoogle();

    expect($usuario->fresh()->google_id)->toBeNull();
});

it('no deja entrar con un correo que Google no verificó', function (): void {
    // Cualquiera puede crear una cuenta de Google con el correo de otra
    // persona sin verificarlo.
    $usuario = docenteDeLaUfps();
    googleDevuelve(['email_verified' => false]);

    volverDeGoogle()->assertSessionHasErrors('acceso');

    expect(Auth::check())->toBeFalse()
        ->and($usuario->fresh()->google_id)->toBeNull();
});

it('no deja entrar con una cuenta de fuera del dominio institucional', function (?string $dominio): void {
    // "hd" es solo una sugerencia a Google: quien la quita de la URL puede
    // volver con cualquier cuenta. Se comprueba aquí.
    docenteDeLaUfps();
    googleDevuelve(['hd' => $dominio]);

    volverDeGoogle()->assertSessionHasErrors(['acceso' => 'Entra con tu cuenta institucional, la que termina en @ufps.edu.co.']);

    expect(Auth::check())->toBeFalse();
})->with([
    'cuenta personal de Gmail' => [null],
    'otro dominio' => ['otra-universidad.edu.co'],
]);

it('acepta cualquier dominio si no se configuró ninguno', function (): void {
    config(['services.google.dominio' => null]);
    $usuario = docenteDeLaUfps();
    googleDevuelve(['hd' => null]);

    volverDeGoogle()->assertRedirect(route('panel.inicio'));

    expect(Auth::id())->toBe($usuario->id);
});

it('no entrega una cuenta ya vinculada a otra identidad de Google', function (): void {
    // El correo coincide, pero la cuenta pertenece a otro "sub": si el
    // correo cambió de dueño, el nuevo no puede heredar la cuenta.
    $usuario = docenteDeLaUfps(['google_id' => '111111111111111111111']);
    googleDevuelve();

    volverDeGoogle()->assertSessionHasErrors(['acceso' => 'Ese correo ya está vinculado a otra cuenta de Google. Comunícate con el laboratorio.']);

    expect(Auth::check())->toBeFalse()
        ->and($usuario->fresh()->google_id)->toBe('111111111111111111111');
});

// ---------------------------------------------------------------------
// La vuelta: la sesión
// ---------------------------------------------------------------------

it('no deja nada de la sesión anterior al entrar', function (): void {
    // Un equipo compartido de la facultad: lo que dejó quien lo usó antes
    // no puede quedar en la sesión de quien entra.
    docenteDeLaUfps();
    googleDevuelve();
    $this->withSession(['dato_de_otra_persona' => 'x']);
    $this->get(route('acceso'));
    $antes = session()->getId();

    volverDeGoogle();

    expect(session()->getId())->not->toBe($antes)
        ->and(session('dato_de_otra_persona'))->toBeNull();
});

it('entra con el rol permanente, no con el que quedó de otra sesión', function (): void {
    // Regla 13: al entrar se ve el rol permanente.
    docenteDeLaUfps();
    googleDevuelve();

    $this->withSession(['rol_activo' => Rol::Coordinador->value]);
    volverDeGoogle();

    expect(session('rol_activo'))->toBeNull();
});

it('devuelve a la pantalla que se pidió antes de entrar', function (): void {
    docenteDeLaUfps();
    googleDevuelve();

    $this->get(route('panel.mis-solicitudes'))->assertRedirect(route('acceso'));

    volverDeGoogle()->assertRedirect(route('panel.mis-solicitudes'));
});

it('explica que se canceló si la persona no dio permiso en Google', function (): void {
    Socialite::shouldReceive('driver')->never();

    $this->get(route('acceso.google.volver', ['error' => 'access_denied', 'state' => 'estado']))
        ->assertRedirect(route('acceso'))
        ->assertSessionHasErrors(['acceso' => 'No se completó el ingreso con Google. Puedes intentarlo de nuevo.']);
});

it('explica que caducó si el "state" no coincide con el de la sesión', function (): void {
    // Sin simular nada: Socialite real compara el state y lo rechaza.
    volverDeGoogle()
        ->assertRedirect(route('acceso'))
        ->assertSessionHasErrors(['acceso' => 'El ingreso con Google caducó. Vuelve a intentarlo.']);

    expect(Auth::check())->toBeFalse();
});

it('enseña en la pantalla de entrada por qué no se pudo entrar', function (): void {
    googleDevuelve(['email' => 'desconocido@ufps.edu.co']);

    $this->followingRedirects()
        ->get(route('acceso.google.volver', ['code' => 'codigo', 'state' => 'estado']))
        ->assertOk()
        ->assertSee('No hay una cuenta de la plataforma para ese correo.');
});

it('da por no verificado el correo si Socialite no trae los datos crudos de Google', function (): void {
    // Nadie entra por un dato que falta.
    $cuenta = Mockery::mock(Laravel\Socialite\Contracts\User::class);
    $cuenta->shouldReceive('getId')->andReturn('109876543210987654321');
    $cuenta->shouldReceive('getEmail')->andReturn('laura.perez@ufps.edu.co');

    $identidad = IdentidadDeGoogle::desdeSocialite($cuenta);

    expect($identidad->emailVerificado)->toBeFalse()
        ->and($identidad->dominio)->toBeNull();
});
