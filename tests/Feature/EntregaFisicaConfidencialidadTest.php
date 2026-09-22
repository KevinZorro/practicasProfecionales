<?php

declare(strict_types=1);

use App\Enums\EstadoFormatoConfidencialidad;
use App\Enums\Rol;
use App\Exceptions\FormatoConfidencialidadInvalido;
use App\Livewire\Confidencialidad\EstadoDeFirmantes;
use App\Models\FormatoConfidencialidad;
use App\Models\PlantillaConfidencialidad;
use App\Models\User;
use App\Services\ConfidencialidadService;
use Database\Seeders\RolSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    Storage::fake('local');
    $this->seed(RolSeeder::class);
    config(['laboratorio.periodo_academico.vigente' => '2026-2']);
    $this->servicio = app(ConfidencialidadService::class);
    $this->administrativo = User::factory()->administrativo()->create();
    $this->coordinadora = User::factory()->coordinador()->create();
    $this->estudiante = User::factory()->estudiante()->create();
    PlantillaConfidencialidad::factory()->create();
});

/** El PDF que sube el estudiante cuando por fin escanea el formato. */
function escaneoDelFormato(): UploadedFile
{
    return UploadedFile::fake()->create('formato-confidencialidad.pdf', 200, 'application/pdf');
}

// ---------------------------------------------------------------------
// Quién puede registrarla
// ---------------------------------------------------------------------

it('deja al administrativo registrar la entrega en físico', function (): void {
    // Es quien recibe el formato en la puerta del laboratorio.
    $entrega = $this->servicio->registrarEntregaFisica($this->estudiante, $this->administrativo);

    expect($entrega->entregadoEnFisico())->toBeTrue()
        ->and($entrega->recibido_fisico_por)->toBe($this->administrativo->id)
        ->and($entrega->recibido_fisico_at)->not->toBeNull()
        ->and($entrega->estado)->toBe(EstadoFormatoConfidencialidad::Pendiente)
        ->and($entrega->periodo_academico)->toBe('2026-2');
});

it('no deja al estudiante marcarse la entrega en físico a sí mismo', function (): void {
    expect(fn () => $this->servicio->registrarEntregaFisica($this->estudiante, $this->estudiante))
        ->toThrow(AuthorizationException::class);

    expect(FormatoConfidencialidad::count())->toBe(0);
});

it('no deja a un docente registrar entregas en físico', function (): void {
    $docente = User::factory()->docente()->create();

    expect(fn () => $this->servicio->registrarEntregaFisica($this->estudiante, $docente))
        ->toThrow(AuthorizationException::class);
});

it('deja registrarla también a quien hereda el permiso del administrativo', function (string $quien): void {
    $entrega = $this->servicio->registrarEntregaFisica($this->estudiante, $this->$quien);

    expect($entrega->entregadoEnFisico())->toBeTrue();
})->with(['coordinadora']);

it('no pisa el registro de quién recibió el formato', function (): void {
    $primera = $this->servicio->registrarEntregaFisica($this->estudiante, $this->administrativo);
    $otro = User::factory()->administrativo()->create();

    expect(fn () => $this->servicio->registrarEntregaFisica($this->estudiante, $otro))
        ->toThrow(FormatoConfidencialidadInvalido::class, 'ya estaba registrada');

    expect($primera->fresh()->recibido_fisico_por)->toBe($this->administrativo->id);
});

// ---------------------------------------------------------------------
// Ingreso a prácticas (RF53)
// ---------------------------------------------------------------------

it('habilita el ingreso a prácticas con la entrega en físico', function (): void {
    expect($this->servicio->puedeParticiparEnPracticas($this->estudiante))->toBeFalse();

    $this->servicio->registrarEntregaFisica($this->estudiante, $this->administrativo);

    expect($this->servicio->puedeParticiparEnPracticas($this->estudiante))->toBeTrue();
});

it('no da por cerrado el trámite solo con la entrega en físico', function (): void {
    // Puede entrar, pero el documento sigue sin verificar: es la lista que
    // la administrativa persigue durante los tres días siguientes.
    $this->servicio->registrarEntregaFisica($this->estudiante, $this->administrativo);

    expect($this->servicio->puedeParticiparEnPracticas($this->estudiante))->toBeTrue()
        ->and($this->servicio->tieneFormatoVigente($this->estudiante))->toBeFalse();
});

it('no habilita el ingreso con una entrega en físico de otro periodo', function (): void {
    FormatoConfidencialidad::factory()->entregadoEnFisico()->delPeriodo('2026-1')->create([
        'firmante_id' => $this->estudiante->id,
    ]);

    expect($this->servicio->puedeParticiparEnPracticas($this->estudiante))->toBeFalse();
});

it('sigue habilitando el ingreso al que tiene el documento verificado', function (): void {
    FormatoConfidencialidad::factory()->verificado()->delPeriodo('2026-2')->create([
        'firmante_id' => $this->estudiante->id,
    ]);

    expect($this->servicio->puedeParticiparEnPracticas($this->estudiante))->toBeTrue();
});

// ---------------------------------------------------------------------
// El escaneo llega después, sin perder el registro de la entrega
// ---------------------------------------------------------------------

it('conserva la entrega en físico al subir el escaneo', function (): void {
    $this->servicio->registrarEntregaFisica($this->estudiante, $this->administrativo);

    $cargada = $this->servicio->registrarEntrega($this->estudiante, escaneoDelFormato());

    expect($cargada->estado)->toBe(EstadoFormatoConfidencialidad::Cargado)
        ->and($cargada->entregadoEnFisico())->toBeTrue()
        ->and($cargada->recibido_fisico_por)->toBe($this->administrativo->id);
});

it('conserva la entrega en físico al verificar el documento', function (): void {
    $this->servicio->registrarEntregaFisica($this->estudiante, $this->administrativo);
    $cargada = $this->servicio->registrarEntrega($this->estudiante, escaneoDelFormato());

    $verificada = $this->servicio->verificar($cargada, $this->coordinadora);

    expect($verificada->estado)->toBe(EstadoFormatoConfidencialidad::Verificado)
        ->and($verificada->entregadoEnFisico())->toBeTrue()
        ->and($verificada->recibido_fisico_por)->toBe($this->administrativo->id)
        ->and($verificada->verificado_por)->toBe($this->coordinadora->id);
});

it('conserva la entrega en físico aunque le devuelvan el escaneo', function (): void {
    // El papel se recibió igual: devolver el archivo no borra ese hecho.
    $this->servicio->registrarEntregaFisica($this->estudiante, $this->administrativo);
    $cargada = $this->servicio->registrarEntrega($this->estudiante, escaneoDelFormato());

    $rechazada = $this->servicio->rechazar($cargada, $this->coordinadora, 'La firma no se lee.');

    expect($rechazada->estado)->toBe(EstadoFormatoConfidencialidad::Pendiente)
        ->and($rechazada->entregadoEnFisico())->toBeTrue()
        ->and($this->servicio->puedeParticiparEnPracticas($this->estudiante))->toBeTrue();
});

it('registra la entrega en físico sobre un documento ya cargado', function (): void {
    // El orden real puede ser el contrario: subió el escaneo y trajo el
    // papel después. El enum lineal no lo permitiría.
    $this->servicio->registrarEntrega($this->estudiante, escaneoDelFormato());

    $entrega = $this->servicio->registrarEntregaFisica($this->estudiante, $this->administrativo);

    expect($entrega->estado)->toBe(EstadoFormatoConfidencialidad::Cargado)
        ->and($entrega->entregadoEnFisico())->toBeTrue();
});

// ---------------------------------------------------------------------
// La pantalla
// ---------------------------------------------------------------------

it('deja al administrativo registrar la entrega desde la pantalla', function (): void {
    Livewire::actingAs($this->administrativo)
        ->test(EstadoDeFirmantes::class)
        ->call('marcarEntregaFisica', $this->estudiante->id);

    expect($this->servicio->entregaDelPeriodo($this->estudiante)?->entregadoEnFisico())->toBeTrue();
});

it('no deja a un estudiante ni a un docente acercarse a la pantalla ni al permiso', function (Rol $rol): void {
    // El componente comprueba el permiso otra vez dentro del método, pero
    // hoy eso es defensa en profundidad y no se puede probar: quien entra a
    // esta pantalla (administrativo, coordinación, ADMIN) es exactamente
    // quien puede marcar la entrega. Lo que de verdad los detiene es esto.
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);

    expect($usuario->fresh()->can('marcarEntregaFisica', FormatoConfidencialidad::class))->toBeFalse();

    $this->actingAs($usuario->fresh())
        ->get(route('panel.formatos-confidencialidad.estado'))
        ->assertForbidden();

    expect(FormatoConfidencialidad::count())->toBe(0);
})->with([Rol::Estudiante, Rol::Docente]);

it('enseña quién recibió el formato y que falta el escaneo', function (): void {
    $this->servicio->registrarEntregaFisica($this->estudiante, $this->administrativo);

    Livewire::actingAs($this->administrativo)
        ->test(EstadoDeFirmantes::class)
        ->assertSee('Entregado en físico')
        ->assertSee('Recibido por '.$this->administrativo->nombre)
        ->assertSee('falta que suba el escaneo');
});

it('avisa en pantalla si la entrega ya estaba registrada', function (): void {
    $this->servicio->registrarEntregaFisica($this->estudiante, $this->administrativo);

    Livewire::actingAs($this->administrativo)
        ->test(EstadoDeFirmantes::class)
        ->call('marcarEntregaFisica', $this->estudiante->id)
        ->assertSee('ya estaba registrada');
});
