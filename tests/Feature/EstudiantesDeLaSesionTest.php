<?php

declare(strict_types=1);

use App\Enums\EstadoUsuario;
use App\Exceptions\SolicitudInvalida;
use App\Livewire\Solicitud\BandejaRevision;
use App\Livewire\Solicitud\FormularioSolicitud;
use App\Models\CasoClinico;
use App\Models\Materia;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\SolicitudService;
use Database\Seeders\RolSeeder;
use Livewire\Livewire;

/*
 * Grupo y estudiantes de cada sesión (RF28): el docente dice qué
 * estudiantes van, y la cantidad sale de esa lista.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->servicio = app(SolicitudService::class);
    $this->docente = User::factory()->docente()->create();
});

it('guarda el grupo y los estudiantes, y cuenta la cantidad de la lista', function (): void {
    $ids = idsDeEstudiantes(4);

    $solicitud = $this->servicio->crear($this->docente, sesionCon([...$ids, $ids[0]], ' c '));

    expect($solicitud->grupo)->toBe('C')
        ->and($solicitud->cantidad_estudiantes)->toBe(4)
        ->and($solicitud->estudiantes()->pluck('users.id')->sort()->values()->all())->toBe(collect($ids)->sort()->values()->all());
});

it('no crea una sesión sin estudiantes', function (): void {
    expect(fn () => $this->servicio->crear($this->docente, sesionCon([])))
        ->toThrow(SolicitudInvalida::class, 'qué estudiantes');
});

it('no acepta un grupo que no sea una o dos letras', function (string $grupo): void {
    expect(fn () => $this->servicio->crear($this->docente, sesionCon(idsDeEstudiantes(1), $grupo)))
        ->toThrow(SolicitudInvalida::class, 'grupo');
})->with(['vacío' => '', 'número' => '1', 'tres letras' => 'ABC', 'con signo' => 'A-']);

it('no deja incluir a quien no es estudiante ni a un estudiante inactivo', function (): void {
    $otroDocente = User::factory()->docente()->create(['nombre' => 'Docente Colado']);
    $egresado = User::factory()->estudiante()->create(['nombre' => 'Egresado Inactivo', 'estado' => EstadoUsuario::Inactivo]);

    expect(fn () => $this->servicio->crear($this->docente, sesionCon([...idsDeEstudiantes(2), $otroDocente->id, $egresado->id])))
        ->toThrow(SolicitudInvalida::class, 'Docente Colado, Egresado Inactivo');

    expect(Solicitud::count())->toBe(0);
});

it('busca estudiantes activos por nombre o código, sin los que ya están en la lista', function (): void {
    $ana = User::factory()->estudiante()->create(['nombre' => 'Ana Ruiz', 'codigo_institucional' => '1151001']);
    $andres = User::factory()->estudiante()->create(['nombre' => 'Andrés Ruiz', 'codigo_institucional' => '1151002']);
    User::factory()->estudiante()->create(['nombre' => 'Ruiz Inactivo', 'estado' => EstadoUsuario::Inactivo]);
    User::factory()->docente()->create(['nombre' => 'Profesor Ruiz']);

    expect($this->servicio->buscarEstudiantes('ruiz')->pluck('id')->all())->toBe([$ana->id, $andres->id])
        ->and($this->servicio->buscarEstudiantes('1151002')->pluck('id')->all())->toBe([$andres->id])
        ->and($this->servicio->buscarEstudiantes('ruiz', [$ana->id])->pluck('id')->all())->toBe([$andres->id])
        ->and($this->servicio->buscarEstudiantes('  '))->toBeEmpty();
});

// ---------------------------------------------------------------------
// El formulario
// ---------------------------------------------------------------------

it('agrega estudiantes desde la búsqueda y los quita', function (): void {
    $ana = User::factory()->estudiante()->create(['nombre' => 'Ana Ruiz']);

    Livewire::actingAs($this->docente)
        ->test(FormularioSolicitud::class)
        ->set('busquedaEstudiante', 'Ana')
        ->assertSee('Ana Ruiz')
        ->call('agregarEstudiante', $ana->id)
        ->assertSet('estudianteIds', [$ana->id])
        ->assertSet('busquedaEstudiante', '')
        ->assertSee('1 estudiante')
        ->call('quitarEstudiante', $ana->id)
        ->assertSet('estudianteIds', []);
});

it('agrega de una vez los códigos pegados y dice cuáles no encontró', function (): void {
    $ana = User::factory()->estudiante()->create(['codigo_institucional' => '1151001']);
    $luis = User::factory()->estudiante()->create(['codigo_institucional' => '1151002']);

    Livewire::actingAs($this->docente)
        ->test(FormularioSolicitud::class)
        ->set('codigosPegados', "1151001, 1151002\n9999999")
        ->call('agregarPorCodigo')
        ->assertSet('codigosPegados', '')
        ->assertSee('Estos códigos no son de ningún estudiante activo: 9999999.')
        ->assertSee('2 estudiantes');
});

it('crea la sesión con los estudiantes elegidos en el formulario', function (): void {
    $ids = idsDeEstudiantes(3);

    Livewire::actingAs($this->docente)
        ->test(FormularioSolicitud::class)
        ->set('materiaId', Materia::factory()->create()->id)
        ->set('casoClinicoId', CasoClinico::factory()->create()->id)
        ->set('fecha', '2026-10-20')
        ->set('horaInicio', '07:00')
        ->set('horaFin', '09:00')
        ->set('grupo', 'a')
        ->set('estudianteIds', $ids)
        ->call('guardar')
        ->assertHasNoErrors();

    $solicitud = Solicitud::firstOrFail();

    expect($solicitud->grupo)->toBe('A')
        ->and($solicitud->cantidad_estudiantes)->toBe(3);
});

it('rechaza en el formulario un grupo con números', function (): void {
    Livewire::actingAs($this->docente)
        ->test(FormularioSolicitud::class)
        ->set('grupo', '2')
        ->call('guardar')
        ->assertHasErrors(['grupo' => 'regex']);
});

it('enseña el grupo y los estudiantes a quien revisa la solicitud', function (): void {
    $ana = User::factory()->estudiante()->create(['nombre' => 'Ana Ruiz']);
    $solicitud = $this->servicio->crear($this->docente, sesionCon([$ana->id], 'B'));

    Livewire::actingAs(User::factory()->administrativo()->create())
        ->test(BandejaRevision::class)
        ->assertSee('1 · grupo B')
        ->call('abrir', $solicitud->id)
        ->assertSee('Estudiantes de la sesión')
        ->assertSee('Ana Ruiz');
});
