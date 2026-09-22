<?php

declare(strict_types=1);

use App\Enums\EstadoFormatoConfidencialidad;
use App\Livewire\Confidencialidad\BandejaVerificacion;
use App\Livewire\Confidencialidad\EstadoDeFirmantes;
use App\Livewire\Confidencialidad\GestionDePlantilla;
use App\Livewire\Confidencialidad\MiFormato;
use App\Models\FormatoConfidencialidad;
use App\Models\PlantillaConfidencialidad;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    Storage::fake('local');
    $this->seed(RolSeeder::class);
    config(['laboratorio.periodo_academico.vigente' => '2026-2']);

    $this->estudiante = User::factory()->estudiante()->create();
    $this->coordinadora = User::factory()->coordinador()->create();
    $this->admin = User::factory()->admin()->create();
    $this->administrativo = User::factory()->administrativo()->create();
});

afterEach(function (): void {
    Model::preventLazyLoading(false);
});

/** Un PDF de prueba. */
function pdfFirmado(int $kb = 200): UploadedFile
{
    return UploadedFile::fake()->create('formato-confidencialidad.pdf', $kb, 'application/pdf');
}

/** Deja una plantilla vigente. */
function plantillaVigente(): PlantillaConfidencialidad
{
    return PlantillaConfidencialidad::factory()->create();
}

/**
 * Consultas que dispara pintar la pantalla.
 *
 * Hace falta contar, y no basta con Model::preventLazyLoading(): el
 * hasRole() de spatie llama a loadMissing('roles'), que es carga ansiosa y
 * por tanto no viola nada aunque se ejecute una vez por fila. Comparando el
 * conteo con dos números de filas, una consulta por fila salta y el número
 * exacto de consultas base no importa.
 */
function consultasAlPintar(Closure $pintar): int
{
    $consultas = 0;
    DB::listen(function () use (&$consultas): void {
        $consultas++;
    });

    $pintar();

    return $consultas;
}

// ---------------------------------------------------------------------
// Estudiante
// ---------------------------------------------------------------------

it('deja al estudiante subir su formato y lo deja cargado', function (): void {
    plantillaVigente();

    Livewire::actingAs($this->estudiante)
        ->test(MiFormato::class)
        ->set('documento', pdfFirmado())
        ->call('entregar')
        ->assertHasNoErrors()
        ->assertSet('errorDeRegla', null);

    $entrega = FormatoConfidencialidad::firstOrFail();
    expect($entrega->firmante_id)->toBe($this->estudiante->id)
        ->and($entrega->estado)->toBe(EstadoFormatoConfidencialidad::Cargado)
        ->and($entrega->periodo_academico)->toBe('2026-2');
});

it('rechaza con un mensaje claro un archivo que no es PDF', function (): void {
    plantillaVigente();

    Livewire::actingAs($this->estudiante)
        ->test(MiFormato::class)
        ->set('documento', UploadedFile::fake()->image('firma.jpg'))
        ->call('entregar')
        ->assertHasErrors(['documento' => 'mimes'])
        ->assertSee('debe ser un archivo PDF');

    expect(FormatoConfidencialidad::count())->toBe(0);
});

it('rechaza un PDF que pasa del tamaño permitido', function (): void {
    plantillaVigente();
    $maximo = (int) config('laboratorio.confidencialidad.tamano_maximo_kb');

    Livewire::actingAs($this->estudiante)
        ->test(MiFormato::class)
        ->set('documento', pdfFirmado($maximo + 1))
        ->call('entregar')
        ->assertHasErrors(['documento' => 'max']);
});

it('avisa al estudiante si todavía no hay plantilla publicada', function (): void {
    Livewire::actingAs($this->estudiante)
        ->test(MiFormato::class)
        ->assertSee('Todavía no hay documento disponible');
});

it('explica que el formato se renueva cada semestre', function (): void {
    // Un estudiante que entregó el semestre pasado tiene que entender por
    // qué se lo piden otra vez.
    FormatoConfidencialidad::factory()->verificado()->delPeriodo('2026-1')->create([
        'firmante_id' => $this->estudiante->id,
    ]);

    Livewire::actingAs($this->estudiante)
        ->test(MiFormato::class)
        ->assertSee('se renueva cada semestre')
        ->assertSee('no vale para 2026-2');
});

it('no da por vigente el formato verificado del periodo anterior', function (): void {
    FormatoConfidencialidad::factory()->verificado()->delPeriodo('2026-1')->create([
        'firmante_id' => $this->estudiante->id,
    ]);
    plantillaVigente();

    Livewire::actingAs($this->estudiante)
        ->test(MiFormato::class)
        ->assertViewHas('entrega', null)
        ->assertSee('Todavía no has entregado el formato de este periodo')
        ->assertSee('Sube el documento firmado');
});

it('enseña al estudiante el motivo del rechazo y le deja volver a subirlo', function (): void {
    plantillaVigente();
    FormatoConfidencialidad::factory()->delPeriodo('2026-2')->create([
        'firmante_id' => $this->estudiante->id,
        'estado' => EstadoFormatoConfidencialidad::Pendiente,
        'motivo_rechazo' => 'Falta la firma en la segunda página.',
    ]);

    Livewire::actingAs($this->estudiante)
        ->test(MiFormato::class)
        ->assertSee('Tu documento anterior fue devuelto')
        ->assertSee('Falta la firma en la segunda página.')
        ->assertSee('Sube el documento firmado')
        ->set('documento', pdfFirmado())
        ->call('entregar')
        ->assertHasNoErrors();

    expect(FormatoConfidencialidad::firstOrFail()->estado)->toBe(EstadoFormatoConfidencialidad::Cargado);
});

// ---------------------------------------------------------------------
// Verificación
// ---------------------------------------------------------------------

it('deja verificar a quien tiene el permiso', function (string $quien): void {
    $entrega = FormatoConfidencialidad::factory()->cargado()->delPeriodo('2026-2')->create([
        'firmante_id' => $this->estudiante->id,
    ]);

    Livewire::actingAs($this->$quien)
        ->test(BandejaVerificacion::class)
        ->call('verificar', $entrega->id);

    expect($entrega->fresh()->estado)->toBe(EstadoFormatoConfidencialidad::Verificado);
})->with(['administrativo', 'coordinadora', 'admin']);

it('deja al administrativo entrar a la bandeja de verificación', function (): void {
    // Es quien recibe y revisa las entregas en la operación diaria.
    $this->actingAs($this->administrativo)->get(route('panel.formatos-confidencialidad'))->assertOk();
    $this->actingAs($this->administrativo)->get(route('panel.formatos-confidencialidad.estado'))->assertOk();
});

it('no deja al docente verificar ni rechazar aunque llame al método', function (string $accion): void {
    $entrega = FormatoConfidencialidad::factory()->cargado()->delPeriodo('2026-2')->create([
        'firmante_id' => $this->estudiante->id,
    ]);
    $docente = User::factory()->docente()->create();

    Livewire::actingAs($docente)
        ->test(BandejaVerificacion::class)
        ->call($accion, $entrega->id)
        ->assertForbidden();

    expect($entrega->fresh()->estado)->toBe(EstadoFormatoConfidencialidad::Cargado);
})->with(['verificar', 'rechazar']);

it('devuelve el formato a pendiente con el motivo', function (): void {
    $entrega = FormatoConfidencialidad::factory()->cargado()->delPeriodo('2026-2')->create([
        'firmante_id' => $this->estudiante->id,
    ]);

    Livewire::actingAs($this->coordinadora)
        ->test(BandejaVerificacion::class)
        ->call('pedirMotivo', $entrega->id)
        ->set('motivoRechazo', 'El PDF está ilegible.')
        ->call('rechazar', $entrega->id);

    $entrega->refresh();
    expect($entrega->estado)->toBe(EstadoFormatoConfidencialidad::Pendiente)
        ->and($entrega->motivo_rechazo)->toBe('El PDF está ilegible.')
        ->and($entrega->archivo_firmado_path)->toBeNull();
});

it('filtra la bandeja por estado y por periodo', function (): void {
    FormatoConfidencialidad::factory()->cargado()->delPeriodo('2026-2')->create();
    FormatoConfidencialidad::factory()->verificado()->delPeriodo('2026-2')->create();
    FormatoConfidencialidad::factory()->cargado()->delPeriodo('2026-1')->create();

    Livewire::actingAs($this->coordinadora)
        ->test(BandejaVerificacion::class)
        ->assertViewHas('entregas', fn ($p): bool => $p->total() === 1)
        ->set('estado', '')
        ->assertViewHas('entregas', fn ($p): bool => $p->total() === 2)
        ->set('periodo', '2026-1')
        ->assertViewHas('entregas', fn ($p): bool => $p->total() === 1);
});

it('no genera consultas N+1 al recorrer la bandeja', function (): void {
    FormatoConfidencialidad::factory()->count(6)->cargado()->delPeriodo('2026-2')->create();

    Model::preventLazyLoading();

    Livewire::actingAs($this->coordinadora)->test(BandejaVerificacion::class)->assertOk();
});

// ---------------------------------------------------------------------
// Estado general
// ---------------------------------------------------------------------

it('enseña quién tiene el formato al día y quién no', function (): void {
    $alDia = User::factory()->estudiante()->create(['nombre' => 'Ana Al Día']);
    FormatoConfidencialidad::factory()->verificado()->delPeriodo('2026-2')->create(['firmante_id' => $alDia->id]);
    User::factory()->estudiante()->create(['nombre' => 'Beto Sin Entregar']);

    Livewire::actingAs($this->coordinadora)
        ->test(EstadoDeFirmantes::class)
        ->assertSee('Ana Al Día')
        ->assertSee('Beto Sin Entregar')
        ->assertSee('Sin entregar')
        ->set('situacion', EstadoDeFirmantes::LE_FALTA)
        ->assertSee('Beto Sin Entregar')
        ->assertDontSee('Ana Al Día')
        ->set('situacion', EstadoDeFirmantes::AL_DIA)
        ->assertSee('Ana Al Día')
        ->assertDontSee('Beto Sin Entregar');
});

it('no cuenta como al día un verificado del periodo anterior', function (): void {
    $estudiante = User::factory()->estudiante()->create(['nombre' => 'Carla Semestre Pasado']);
    FormatoConfidencialidad::factory()->verificado()->delPeriodo('2026-1')->create(['firmante_id' => $estudiante->id]);

    Livewire::actingAs($this->coordinadora)
        ->test(EstadoDeFirmantes::class)
        ->set('situacion', EstadoDeFirmantes::LE_FALTA)
        ->assertSee('Carla Semestre Pasado');
});

it('busca estudiantes por nombre y correo', function (): void {
    User::factory()->estudiante()->create(['nombre' => 'Ana Gómez', 'email' => 'ana@u.edu.co']);
    User::factory()->estudiante()->create(['nombre' => 'Beto Ruiz', 'email' => 'beto@u.edu.co']);

    Livewire::actingAs($this->coordinadora)
        ->test(EstadoDeFirmantes::class)
        ->set('busqueda', 'gómez')
        ->assertSee('Ana Gómez')
        ->assertDontSee('Beto Ruiz')
        ->set('busqueda', 'beto@u.edu.co')
        ->assertSee('Beto Ruiz')
        ->assertDontSee('Ana Gómez');
});

it('lista a quien firma el formato y deja fuera a quien no', function (): void {
    $docente = User::factory()->docente()->create(['nombre' => 'Diana Docente']);

    Livewire::actingAs($this->coordinadora)
        ->test(EstadoDeFirmantes::class)
        ->assertSee($this->estudiante->nombre)
        ->assertSee('Diana Docente')
        ->assertDontSee($this->administrativo->nombre);
});

it('distingue al docente de los estudiantes en la lista', function (): void {
    User::factory()->docente()->create(['nombre' => 'Diana Docente']);

    Livewire::actingAs($this->coordinadora)
        ->test(EstadoDeFirmantes::class)
        ->assertSee('Diana Docente')
        ->assertSee('Docente');
});

it('busca por código institucional', function (): void {
    User::factory()->estudiante()->create(['nombre' => 'Ana Gómez', 'codigo_institucional' => 'EST-1234']);
    User::factory()->estudiante()->create(['nombre' => 'Beto Ruiz', 'codigo_institucional' => 'EST-9876']);

    Livewire::actingAs($this->coordinadora)
        ->test(EstadoDeFirmantes::class)
        ->set('busqueda', 'est-1234')
        ->assertSee('Ana Gómez')
        ->assertDontSee('Beto Ruiz');
});

it('deja registrar la entrega en físico de un docente', function (): void {
    plantillaVigente();
    $docente = User::factory()->docente()->create(['nombre' => 'Diana Docente']);

    Livewire::actingAs($this->administrativo)
        ->test(EstadoDeFirmantes::class)
        ->call('marcarEntregaFisica', $docente->id)
        ->assertHasNoErrors();

    expect($docente->formatosDeConfidencialidad()->delPeriodo('2026-2')->first()?->entregadoEnFisico())->toBeTrue();
});

it('deja al docente subir su propio formato', function (): void {
    plantillaVigente();
    $docente = User::factory()->docente()->create();

    Livewire::actingAs($docente)
        ->test(MiFormato::class)
        ->set('documento', pdfFirmado())
        ->call('entregar')
        ->assertHasNoErrors();

    expect($docente->formatosDeConfidencialidad()->delPeriodo('2026-2')->first()?->estado)
        ->toBe(EstadoFormatoConfidencialidad::Cargado);
});

it('no genera consultas N+1 al recorrer el estado de los firmantes', function (): void {
    $sembrar = function (int $cuantos): void {
        User::factory()->count($cuantos)->docente()->create()
            ->each(fn (User $u) => FormatoConfidencialidad::factory()->verificado()->delPeriodo('2026-2')->create([
                'firmante_id' => $u->id,
            ]));
    };

    Model::preventLazyLoading();
    $pintar = fn () => Livewire::actingAs($this->coordinadora)->test(EstadoDeFirmantes::class)->assertOk();

    // Sin este primer pintado la medición sale torcida: la caché de permisos
    // de spatie se llena en la primera pasada y la segunda cuenta menos.
    $sembrar(2);
    $pintar();

    $conDos = consultasAlPintar($pintar);

    $sembrar(6);
    $conOcho = consultasAlPintar($pintar);

    expect($conOcho)->toBe($conDos);
});

it('marca al docente también en la bandeja de verificación', function (): void {
    $docente = User::factory()->docente()->create(['nombre' => 'Diana Docente']);
    FormatoConfidencialidad::factory()->cargado()->delPeriodo('2026-2')->create(['firmante_id' => $docente->id]);

    Livewire::actingAs($this->administrativo)
        ->test(BandejaVerificacion::class)
        ->assertSee('Diana Docente')
        ->assertSee('Docente');
});

it('no genera consultas N+1 al recorrer la bandeja de verificación', function (): void {
    $sembrar = function (int $cuantos): void {
        User::factory()->count($cuantos)->docente()->create()
            ->each(fn (User $u) => FormatoConfidencialidad::factory()->cargado()->delPeriodo('2026-2')->create([
                'firmante_id' => $u->id,
            ]));
    };

    Model::preventLazyLoading();
    $pintar = fn () => Livewire::actingAs($this->administrativo)->test(BandejaVerificacion::class)->assertOk();

    // Igual que arriba: la primera pasada llena la caché de permisos.
    $sembrar(2);
    $pintar();

    $conDos = consultasAlPintar($pintar);

    $sembrar(6);
    $conOcho = consultasAlPintar($pintar);

    expect($conOcho)->toBe($conDos);
});

// ---------------------------------------------------------------------
// Plantilla
// ---------------------------------------------------------------------

it('deja al ADMIN cargar la plantilla y deja solo una vigente', function (): void {
    $anterior = plantillaVigente();

    Livewire::actingAs($this->admin)
        ->test(GestionDePlantilla::class)
        ->set('version', '2026.2')
        ->set('archivo', pdfFirmado())
        ->call('cargar')
        ->assertHasNoErrors();

    expect($anterior->fresh()->activo)->toBeFalse()
        ->and(PlantillaConfidencialidad::where('activo', true)->count())->toBe(1);
});

it('no deja a nadie más cargar la plantilla', function (string $quien): void {
    $this->actingAs($this->$quien)->get(route('panel.plantillas-confidencialidad'))->assertForbidden();
})->with(['coordinadora', 'administrativo', 'estudiante']);

it('marca cuál plantilla está vigente en el historial', function (): void {
    PlantillaConfidencialidad::factory()->inactiva()->create(['version' => '1.0']);
    PlantillaConfidencialidad::factory()->create(['version' => '2.0']);

    Livewire::actingAs($this->admin)
        ->test(GestionDePlantilla::class)
        ->assertSee('Vigente')
        ->assertSee('Retirada');
});

// ---------------------------------------------------------------------
// Los archivos: RNF07
// ---------------------------------------------------------------------

it('no deja a un estudiante descargar el documento de otro', function (): void {
    $otro = User::factory()->estudiante()->create();
    $entrega = FormatoConfidencialidad::factory()->cargado()->delPeriodo('2026-2')->create([
        'firmante_id' => $otro->id,
    ]);

    $this->actingAs($this->estudiante)
        ->get(route('panel.formatos-confidencialidad.firmado', $entrega))
        ->assertForbidden();
});

it('deja al estudiante descargar el suyo', function (): void {
    Storage::disk('local')->put('confidencialidad/firmados/mio.pdf', '%PDF-1.4 contenido');
    $entrega = FormatoConfidencialidad::factory()->cargado()->delPeriodo('2026-2')->create([
        'firmante_id' => $this->estudiante->id,
        'archivo_firmado_path' => 'confidencialidad/firmados/mio.pdf',
    ]);

    $this->actingAs($this->estudiante)
        ->get(route('panel.formatos-confidencialidad.firmado', $entrega))
        ->assertOk()
        ->assertDownload();
});

it('deja descargar el documento a quien lo verifica', function (string $quien): void {
    Storage::disk('local')->put('confidencialidad/firmados/x.pdf', '%PDF-1.4');
    $entrega = FormatoConfidencialidad::factory()->cargado()->delPeriodo('2026-2')->create([
        'firmante_id' => $this->estudiante->id,
        'archivo_firmado_path' => 'confidencialidad/firmados/x.pdf',
    ]);

    $this->actingAs($this->$quien)
        ->get(route('panel.formatos-confidencialidad.firmado', $entrega))
        ->assertOk();
})->with(['administrativo', 'coordinadora', 'admin']);

it('no deja a un docente descargar un documento firmado', function (): void {
    Storage::disk('local')->put('confidencialidad/firmados/x.pdf', '%PDF-1.4');
    $entrega = FormatoConfidencialidad::factory()->cargado()->delPeriodo('2026-2')->create([
        'firmante_id' => $this->estudiante->id,
        'archivo_firmado_path' => 'confidencialidad/firmados/x.pdf',
    ]);

    $usuario = User::factory()->docente()->create();

    $this->actingAs($usuario->fresh())
        ->get(route('panel.formatos-confidencialidad.firmado', $entrega))
        ->assertForbidden();
});

it('no sirve el archivo firmado por enlace directo al disco', function (): void {
    // El disco es privado: la ruta pública de Laravel no lo entrega sin
    // firma, y de todas formas no pasa por la Policy.
    Storage::disk('local')->put('confidencialidad/firmados/secreto.pdf', '%PDF-1.4');

    $this->actingAs($this->estudiante)
        ->get('/storage/confidencialidad/firmados/secreto.pdf')
        ->assertForbidden();
});

it('no deja a un invitado tocar ninguna descarga', function (): void {
    $entrega = FormatoConfidencialidad::factory()->cargado()->create();

    $this->get(route('panel.formatos-confidencialidad.firmado', $entrega))->assertRedirect('/');
    $this->get(route('panel.formatos-confidencialidad.plantilla'))->assertRedirect('/');
});

it('no ofrece verificar ni devolver un formato que no está cargado', function (EstadoFormatoConfidencialidad $estado): void {
    FormatoConfidencialidad::factory()->delPeriodo('2026-2')->create([
        'firmante_id' => $this->estudiante->id,
        'estado' => $estado,
    ]);

    Livewire::actingAs($this->coordinadora)
        ->test(BandejaVerificacion::class)
        ->set('estado', $estado->value)
        ->assertSee($this->estudiante->nombre)
        ->assertDontSee('>Verificar<', escape: false)
        ->assertDontSee('>Devolver<', escape: false);
})->with([EstadoFormatoConfidencialidad::Pendiente, EstadoFormatoConfidencialidad::Verificado]);

it('ofrece verificar y devolver solo cuando está cargado', function (): void {
    FormatoConfidencialidad::factory()->cargado()->delPeriodo('2026-2')->create([
        'firmante_id' => $this->estudiante->id,
    ]);

    Livewire::actingAs($this->coordinadora)
        ->test(BandejaVerificacion::class)
        ->assertSee('Verificar')
        ->assertSee('Devolver');
});
