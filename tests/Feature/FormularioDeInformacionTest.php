<?php

declare(strict_types=1);

use App\Enums\ClaveConfiguracionLanding as Clave;
use App\Events\SolicitudDeInformacionRecibida;
use App\Filament\Pages\ConfiguracionDeLaLanding;
use App\Http\Controllers\SolicitudDeInformacionController;
use App\Http\Requests\SolicitudDeInformacionRequest;
use App\Listeners\EnviarSolicitudDeInformacion;
use App\Mail\SolicitudDeInformacionMail;
use App\Models\ConfiguracionLanding;
use App\Models\SolicitudInformacion;
use App\Models\Taller;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Database\Seeders\RolSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

/*
 * RF09: formulario de solicitud de información por taller. El correo sale
 * de la cuenta del laboratorio y llega al correo de contacto, con responder-a
 * del interesado (D8); límite de envíos por IP y campo trampa (D16). La
 * sección se oculta mientras no esté habilitada.
 */

beforeEach(function (): void {
    Mail::fake();
    RateLimiter::clear('formulario-publico');
    $this->taller = Taller::factory()->create(['titulo' => 'Taller de RCP', 'fecha' => now()->addWeek()->toDateString()]);
});

function configurarLanding(?string $correo = 'laboratorio@ufps.edu.co', bool $formulario = true): void
{
    ConfiguracionLanding::query()->updateOrCreate(['clave' => Clave::ContactoEmail->value], ['valor' => $correo]);
    ConfiguracionLanding::query()->updateOrCreate(['clave' => Clave::FormularioInformacion->value], ['valor' => $formulario ? '1' : null]);
}

/** @return array<string, string> */
function datosDeInteresado(array $cambios = []): array
{
    return [
        'nombre' => 'Laura Pérez',
        'email' => 'laura@example.com',
        'telefono' => '3001234567',
        'mensaje' => '¿Tiene costo?',
        'acepta_tratamiento' => '1',
        ...$cambios,
    ];
}

// ---------------------------------------------------------------------
// Cuándo existe
// ---------------------------------------------------------------------

it('mientras no está habilitado no existe, y el taller abre un correo', function (): void {
    configurarLanding(formulario: false);

    $this->get(route('portada.taller.informacion', $this->taller))->assertNotFound();
    $this->post(route('portada.taller.informacion.enviar', $this->taller), datosDeInteresado())->assertNotFound();
    $this->get('/')->assertOk()->assertSee('mailto:laboratorio@ufps.edu.co', false);

    expect(SolicitudInformacion::count())->toBe(0);
});

it('habilitado pero sin correo de contacto no existe: no habría adonde enviarlo', function (): void {
    configurarLanding(correo: null);

    $this->get(route('portada.taller.informacion', $this->taller))->assertNotFound();
});

it('habilitado, el taller lleva a su formulario', function (): void {
    configurarLanding();

    $this->get('/')->assertOk()
        ->assertSee(route('portada.taller.informacion', $this->taller), false)
        ->assertDontSee('mailto:laboratorio@ufps.edu.co?subject', false);
    $this->get(route('portada.taller.informacion', $this->taller))
        ->assertOk()
        ->assertSee('Taller de RCP')
        ->assertSee('Pedir información');
});

it('un taller que no muestra el formulario sigue abriendo un correo', function (): void {
    configurarLanding();
    $this->taller->update(['muestra_formulario' => false]);

    $this->get(route('portada.taller.informacion', $this->taller))->assertNotFound();
    $this->get('/')->assertOk()->assertSee('mailto:laboratorio@ufps.edu.co', false);
});

it('no existe para un taller sin publicar, ya pasado o inexistente', function (array $cambios): void {
    configurarLanding();
    $this->taller->update($cambios);

    $this->get(route('portada.taller.informacion', $this->taller))->assertNotFound();
})->with([
    'sin publicar' => [['activo' => false]],
    'ya pasado' => [['fecha' => '2020-01-01']],
]);

it('un id que no es un número es un 404, no un 500', function (): void {
    configurarLanding();

    $this->get('/talleres/abc/informacion')->assertNotFound();
});

// ---------------------------------------------------------------------
// El envío
// ---------------------------------------------------------------------

it('guarda la solicitud y la manda al correo de contacto, con responder-a del interesado', function (): void {
    configurarLanding();

    $this->post(route('portada.taller.informacion.enviar', $this->taller), datosDeInteresado())
        ->assertRedirect(route('portada.taller.informacion', $this->taller))
        ->assertSessionHas('enviada', SolicitudDeInformacionController::ENVIADA);

    $solicitud = SolicitudInformacion::sole();
    expect($solicitud->taller_id)->toBe($this->taller->id)
        ->and($solicitud->nombre)->toBe('Laura Pérez')
        ->and($solicitud->telefono)->toBe('3001234567')
        ->and($solicitud->enviado_at)->not->toBeNull();

    Mail::assertSent(SolicitudDeInformacionMail::class, 1);
    Mail::assertSent(SolicitudDeInformacionMail::class, fn (SolicitudDeInformacionMail $correo): bool => $correo->hasTo('laboratorio@ufps.edu.co')
        && $correo->hasReplyTo('laura@example.com')
        && $correo->hasSubject('Información sobre el taller: Taller de RCP'));
});

it('el correo lleva los datos del interesado y el taller', function (): void {
    configurarLanding();
    $this->post(route('portada.taller.informacion.enviar', $this->taller), datosDeInteresado());

    (new SolicitudDeInformacionMail(SolicitudInformacion::sole()))
        ->assertSeeInHtml('Taller de RCP')
        ->assertSeeInHtml('Laura Pérez')
        ->assertSeeInHtml('laura@example.com')
        ->assertSeeInHtml('¿Tiene costo?');
});

it('tras enviar, la página dice que se recibió', function (): void {
    configurarLanding();

    $this->followingRedirects()
        ->post(route('portada.taller.informacion.enviar', $this->taller), datosDeInteresado())
        ->assertOk()
        ->assertSee('Solicitud enviada.');
});

it('exige nombre, correo válido y la autorización de tratamiento de datos', function (string $campo, mixed $valor): void {
    configurarLanding();

    $this->post(route('portada.taller.informacion.enviar', $this->taller), datosDeInteresado([$campo => $valor]))
        ->assertSessionHasErrors($campo);

    expect(SolicitudInformacion::count())->toBe(0);
    Mail::assertNothingSent();
})->with([
    'sin nombre' => ['nombre', ''],
    'correo inválido' => ['email', 'no-es-correo'],
    'sin autorización' => ['acepta_tratamiento', ''],
]);

it('a un robot que llena el campo trampa le responde igual, sin guardar ni enviar', function (): void {
    configurarLanding();

    $this->post(route('portada.taller.informacion.enviar', $this->taller), datosDeInteresado([SolicitudDeInformacionRequest::CAMPO_TRAMPA => 'https://spam.example']))
        ->assertRedirect(route('portada.taller.informacion', $this->taller))
        ->assertSessionHas('enviada', SolicitudDeInformacionController::ENVIADA);

    expect(SolicitudInformacion::count())->toBe(0);
    Mail::assertNothingSent();
});

it('limita los envíos por IP', function (): void {
    configurarLanding();
    $url = route('portada.taller.informacion.enviar', $this->taller);

    for ($i = 0; $i < AppServiceProvider::ENVIOS_DEL_FORMULARIO_PUBLICO_POR_HORA; $i++) {
        $this->post($url, datosDeInteresado())->assertRedirect();
    }

    $this->post($url, datosDeInteresado())->assertStatus(429);
    expect(SolicitudInformacion::count())->toBe(AppServiceProvider::ENVIOS_DEL_FORMULARIO_PUBLICO_POR_HORA);
});

it('un reintento de la cola no repite un correo que ya salió', function (): void {
    configurarLanding();
    $solicitud = SolicitudInformacion::factory()->create(['taller_id' => $this->taller->id, 'enviado_at' => now()]);

    app(EnviarSolicitudDeInformacion::class)->handle(new SolicitudDeInformacionRecibida($solicitud));

    Mail::assertNothingSent();
});

// ---------------------------------------------------------------------
// /admin
// ---------------------------------------------------------------------

it('el ADMIN habilita y oculta el formulario desde la configuración de la landing', function (): void {
    $this->seed(RolSeeder::class);
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(ConfiguracionDeLaLanding::class)
        ->fillForm([Clave::HeroTitulo->value => 'Laboratorio', Clave::FormularioInformacion->value => true])
        ->call('guardar')
        ->assertHasNoFormErrors();

    expect(ConfiguracionLanding::where('clave', Clave::FormularioInformacion->value)->value('valor'))->toBe('1');

    Livewire::actingAs($admin)
        ->test(ConfiguracionDeLaLanding::class)
        ->assertFormSet([Clave::FormularioInformacion->value => true])
        ->fillForm([Clave::FormularioInformacion->value => false])
        ->call('guardar');

    expect(ConfiguracionLanding::where('clave', Clave::FormularioInformacion->value)->value('valor'))->toBeNull();
});
