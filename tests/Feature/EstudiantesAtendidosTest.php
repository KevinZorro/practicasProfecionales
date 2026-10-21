<?php

declare(strict_types=1);

use App\Enums\ClaveConfiguracionLanding as Clave;
use App\Enums\ModoCifraDeEstudiantes;
use App\Filament\Pages\ConfiguracionDeLaLanding;
use App\Models\ConfiguracionLanding;
use App\Models\PeriodoAcademico;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\EstudiantesAtendidosService;
use App\Services\ImagenPublicaService;
use Database\Seeders\RolSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Cifra de estudiantes de la landing: automática (sesiones aprobadas ya
 * realizadas, personas distintas por periodo) o escrita a mano por el ADMIN.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    Storage::fake(ImagenPublicaService::DISCO);
    $this->travelTo(now()->setDate(2026, 10, 21)->setTime(10, 0));
});

/** Una sesión aprobada en `$fecha` con los estudiantes dados en su lista. */
function sesionConEstudiantes(string $fecha, array $estudiantes, array $retirados = []): Solicitud
{
    $sesion = Solicitud::factory()->aprobada()->create(['fecha' => $fecha]);

    foreach ($estudiantes as $estudiante) {
        $sesion->estudiantes()->attach($estudiante->id);
    }
    foreach ($retirados as $estudiante) {
        $sesion->estudiantes()->attach($estudiante->id, ['retirado_at' => now()]);
    }

    return $sesion;
}

function configurarModo(ModoCifraDeEstudiantes $modo, ?string $manual = null): void
{
    ConfiguracionLanding::create(['clave' => Clave::EstudiantesModo->value, 'valor' => $modo->value]);

    if ($manual !== null) {
        ConfiguracionLanding::create(['clave' => Clave::EstudiantesManual->value, 'valor' => $manual]);
    }
}

it('cuenta estudiantes distintos dentro de un periodo', function (): void {
    PeriodoAcademico::factory()->create(['abierto_at' => '2026-08-01 08:00:00']);
    [$ana, $beto] = User::factory()->estudiante()->count(2)->create();

    sesionConEstudiantes('2026-09-01', [$ana, $beto]);
    sesionConEstudiantes('2026-09-15', [$ana]);

    expect(app(EstudiantesAtendidosService::class)->calcular())->toBe(2);
});

it('suma los periodos: quien viene en dos semestres cuenta en los dos', function (): void {
    PeriodoAcademico::factory()->cerrado()->create([
        'abierto_at' => '2026-02-01 08:00:00',
        'cerrado_at' => '2026-06-30 18:00:00',
    ]);
    PeriodoAcademico::factory()->create(['abierto_at' => '2026-08-01 08:00:00']);
    $ana = User::factory()->estudiante()->create();

    sesionConEstudiantes('2026-03-10', [$ana]);
    sesionConEstudiantes('2026-09-10', [$ana]);

    expect(app(EstudiantesAtendidosService::class)->calcular())->toBe(2);
});

it('no cuenta retirados, sesiones futuras, no aprobadas ni sesiones fuera de todo periodo', function (): void {
    PeriodoAcademico::factory()->create(['abierto_at' => '2026-08-01 08:00:00']);
    [$cuenta, $retirado, $futuro, $pendiente, $antes] = User::factory()->estudiante()->count(5)->create();

    sesionConEstudiantes('2026-09-01', [$cuenta], [$retirado]);
    sesionConEstudiantes('2026-12-01', [$futuro]);
    sesionConEstudiantes('2026-07-01', [$antes]);

    $sinAprobar = Solicitud::factory()->create(['fecha' => '2026-09-02']);
    $sinAprobar->estudiantes()->attach($pendiente->id);

    expect(app(EstudiantesAtendidosService::class)->calcular())->toBe(1);
});

it('en modo manual publica la cifra escrita y en automático la calculada', function (): void {
    PeriodoAcademico::factory()->create(['abierto_at' => '2026-08-01 08:00:00']);
    sesionConEstudiantes('2026-09-01', [User::factory()->estudiante()->create()]);
    $servicio = app(EstudiantesAtendidosService::class);

    expect($servicio->modo())->toBe(ModoCifraDeEstudiantes::Automatico)
        ->and($servicio->cifraPublicada())->toBe(1);

    configurarModo(ModoCifraDeEstudiantes::Manual, '3500');

    expect($servicio->cifraPublicada())->toBe(3500);
});

it('muestra la cifra en la portada con separador de miles y la omite en cero', function (): void {
    $this->get('/')->assertOk()->assertDontSee('Estudiantes en el laboratorio');

    configurarModo(ModoCifraDeEstudiantes::Manual, '3500');

    $this->get('/')->assertOk()
        ->assertSee('En cifras.')
        ->assertSee('3.500')
        ->assertSee('Estudiantes en el laboratorio');
});

it('deja al ADMIN elegir el modo y escribir la cifra', function (): void {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(ConfiguracionDeLaLanding::class)
        ->assertFormSet([Clave::EstudiantesModo->value => ModoCifraDeEstudiantes::Automatico->value])
        ->fillForm([
            Clave::HeroTitulo->value => 'Laboratorio',
            Clave::EstudiantesModo->value => ModoCifraDeEstudiantes::Manual->value,
            Clave::EstudiantesManual->value => 1200,
        ])
        ->call('guardar')
        ->assertHasNoFormErrors();

    expect(app(EstudiantesAtendidosService::class)->cifraPublicada())->toBe(1200);
});

it('exige la cifra cuando el modo es manual', function (): void {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(ConfiguracionDeLaLanding::class)
        ->fillForm([
            Clave::HeroTitulo->value => 'Laboratorio',
            Clave::EstudiantesModo->value => ModoCifraDeEstudiantes::Manual->value,
            Clave::EstudiantesManual->value => null,
        ])
        ->call('guardar')
        ->assertHasFormErrors([Clave::EstudiantesManual->value => 'required']);
});
