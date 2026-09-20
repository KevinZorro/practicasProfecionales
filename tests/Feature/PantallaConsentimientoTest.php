<?php

declare(strict_types=1);

use App\Enums\EstadoConsentimiento;
use App\Livewire\Consentimiento\BandejaVerificacion;
use App\Livewire\Consentimiento\EstadoDeEstudiantes;
use App\Livewire\Consentimiento\GestionDePlantilla;
use App\Livewire\Consentimiento\MiConsentimiento;
use App\Models\ConsentimientoEstudiante;
use App\Models\ConsentimientoPlantilla;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
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
    return UploadedFile::fake()->create('consentimiento.pdf', $kb, 'application/pdf');
}

/** Deja una plantilla vigente. */
function plantillaVigente(): ConsentimientoPlantilla
{
    return ConsentimientoPlantilla::factory()->create();
}

// ---------------------------------------------------------------------
// Estudiante
// ---------------------------------------------------------------------

it('deja al estudiante subir su consentimiento y lo deja cargado', function (): void {
    plantillaVigente();

    Livewire::actingAs($this->estudiante)
        ->test(MiConsentimiento::class)
        ->set('documento', pdfFirmado())
        ->call('entregar')
        ->assertHasNoErrors()
        ->assertSet('errorDeRegla', null);

    $entrega = ConsentimientoEstudiante::firstOrFail();
    expect($entrega->estudiante_id)->toBe($this->estudiante->id)
        ->and($entrega->estado)->toBe(EstadoConsentimiento::Cargado)
        ->and($entrega->periodo_academico)->toBe('2026-2');
});

it('rechaza con un mensaje claro un archivo que no es PDF', function (): void {
    plantillaVigente();

    Livewire::actingAs($this->estudiante)
        ->test(MiConsentimiento::class)
        ->set('documento', UploadedFile::fake()->image('firma.jpg'))
        ->call('entregar')
        ->assertHasErrors(['documento' => 'mimes'])
        ->assertSee('debe ser un archivo PDF');

    expect(ConsentimientoEstudiante::count())->toBe(0);
});

it('rechaza un PDF que pasa del tamaño permitido', function (): void {
    plantillaVigente();
    $maximo = (int) config('laboratorio.consentimiento.tamano_maximo_kb');

    Livewire::actingAs($this->estudiante)
        ->test(MiConsentimiento::class)
        ->set('documento', pdfFirmado($maximo + 1))
        ->call('entregar')
        ->assertHasErrors(['documento' => 'max']);
});

it('avisa al estudiante si todavía no hay plantilla publicada', function (): void {
    Livewire::actingAs($this->estudiante)
        ->test(MiConsentimiento::class)
        ->assertSee('Todavía no hay documento disponible');
});

it('explica que el consentimiento se renueva cada semestre', function (): void {
    // Un estudiante que entregó el semestre pasado tiene que entender por
    // qué se lo piden otra vez.
    ConsentimientoEstudiante::factory()->verificado()->delPeriodo('2026-1')->create([
        'estudiante_id' => $this->estudiante->id,
    ]);

    Livewire::actingAs($this->estudiante)
        ->test(MiConsentimiento::class)
        ->assertSee('se renueva cada semestre')
        ->assertSee('no vale para 2026-2');
});

it('no da por vigente el consentimiento verificado del periodo anterior', function (): void {
    ConsentimientoEstudiante::factory()->verificado()->delPeriodo('2026-1')->create([
        'estudiante_id' => $this->estudiante->id,
    ]);
    plantillaVigente();

    Livewire::actingAs($this->estudiante)
        ->test(MiConsentimiento::class)
        ->assertViewHas('entrega', null)
        ->assertSee('Todavía no has entregado el consentimiento de este periodo')
        ->assertSee('Sube el documento firmado');
});

it('enseña al estudiante el motivo del rechazo y le deja volver a subirlo', function (): void {
    plantillaVigente();
    ConsentimientoEstudiante::factory()->delPeriodo('2026-2')->create([
        'estudiante_id' => $this->estudiante->id,
        'estado' => EstadoConsentimiento::Pendiente,
        'motivo_rechazo' => 'Falta la firma en la segunda página.',
    ]);

    Livewire::actingAs($this->estudiante)
        ->test(MiConsentimiento::class)
        ->assertSee('Tu documento anterior fue devuelto')
        ->assertSee('Falta la firma en la segunda página.')
        ->assertSee('Sube el documento firmado')
        ->set('documento', pdfFirmado())
        ->call('entregar')
        ->assertHasNoErrors();

    expect(ConsentimientoEstudiante::firstOrFail()->estado)->toBe(EstadoConsentimiento::Cargado);
});

// ---------------------------------------------------------------------
// Verificación
// ---------------------------------------------------------------------

it('deja verificar a quien tiene el permiso', function (string $quien): void {
    $entrega = ConsentimientoEstudiante::factory()->cargado()->delPeriodo('2026-2')->create([
        'estudiante_id' => $this->estudiante->id,
    ]);

    Livewire::actingAs($this->$quien)
        ->test(BandejaVerificacion::class)
        ->call('verificar', $entrega->id);

    expect($entrega->fresh()->estado)->toBe(EstadoConsentimiento::Verificado);
})->with(['administrativo', 'coordinadora', 'admin']);

it('deja al administrativo entrar a la bandeja de verificación', function (): void {
    // Es quien recibe y revisa las entregas en la operación diaria.
    $this->actingAs($this->administrativo)->get(route('panel.consentimientos'))->assertOk();
    $this->actingAs($this->administrativo)->get(route('panel.consentimientos.estado'))->assertOk();
});

it('no deja al docente verificar ni rechazar aunque llame al método', function (string $accion): void {
    $entrega = ConsentimientoEstudiante::factory()->cargado()->delPeriodo('2026-2')->create([
        'estudiante_id' => $this->estudiante->id,
    ]);
    $docente = User::factory()->docente()->create();

    Livewire::actingAs($docente)
        ->test(BandejaVerificacion::class)
        ->call($accion, $entrega->id)
        ->assertForbidden();

    expect($entrega->fresh()->estado)->toBe(EstadoConsentimiento::Cargado);
})->with(['verificar', 'rechazar']);

it('devuelve el consentimiento a pendiente con el motivo', function (): void {
    $entrega = ConsentimientoEstudiante::factory()->cargado()->delPeriodo('2026-2')->create([
        'estudiante_id' => $this->estudiante->id,
    ]);

    Livewire::actingAs($this->coordinadora)
        ->test(BandejaVerificacion::class)
        ->call('pedirMotivo', $entrega->id)
        ->set('motivoRechazo', 'El PDF está ilegible.')
        ->call('rechazar', $entrega->id);

    $entrega->refresh();
    expect($entrega->estado)->toBe(EstadoConsentimiento::Pendiente)
        ->and($entrega->motivo_rechazo)->toBe('El PDF está ilegible.')
        ->and($entrega->archivo_firmado_path)->toBeNull();
});

it('filtra la bandeja por estado y por periodo', function (): void {
    ConsentimientoEstudiante::factory()->cargado()->delPeriodo('2026-2')->create();
    ConsentimientoEstudiante::factory()->verificado()->delPeriodo('2026-2')->create();
    ConsentimientoEstudiante::factory()->cargado()->delPeriodo('2026-1')->create();

    Livewire::actingAs($this->coordinadora)
        ->test(BandejaVerificacion::class)
        ->assertViewHas('entregas', fn ($p): bool => $p->total() === 1)
        ->set('estado', '')
        ->assertViewHas('entregas', fn ($p): bool => $p->total() === 2)
        ->set('periodo', '2026-1')
        ->assertViewHas('entregas', fn ($p): bool => $p->total() === 1);
});

it('no genera consultas N+1 al recorrer la bandeja', function (): void {
    ConsentimientoEstudiante::factory()->count(6)->cargado()->delPeriodo('2026-2')->create();

    Model::preventLazyLoading();

    Livewire::actingAs($this->coordinadora)->test(BandejaVerificacion::class)->assertOk();
});

// ---------------------------------------------------------------------
// Estado general
// ---------------------------------------------------------------------

it('enseña quién tiene el consentimiento al día y quién no', function (): void {
    $alDia = User::factory()->estudiante()->create(['nombre' => 'Ana Al Día']);
    ConsentimientoEstudiante::factory()->verificado()->delPeriodo('2026-2')->create(['estudiante_id' => $alDia->id]);
    User::factory()->estudiante()->create(['nombre' => 'Beto Sin Entregar']);

    Livewire::actingAs($this->coordinadora)
        ->test(EstadoDeEstudiantes::class)
        ->assertSee('Ana Al Día')
        ->assertSee('Beto Sin Entregar')
        ->assertSee('Sin entregar')
        ->set('situacion', EstadoDeEstudiantes::LE_FALTA)
        ->assertSee('Beto Sin Entregar')
        ->assertDontSee('Ana Al Día')
        ->set('situacion', EstadoDeEstudiantes::AL_DIA)
        ->assertSee('Ana Al Día')
        ->assertDontSee('Beto Sin Entregar');
});

it('no cuenta como al día un verificado del periodo anterior', function (): void {
    $estudiante = User::factory()->estudiante()->create(['nombre' => 'Carla Semestre Pasado']);
    ConsentimientoEstudiante::factory()->verificado()->delPeriodo('2026-1')->create(['estudiante_id' => $estudiante->id]);

    Livewire::actingAs($this->coordinadora)
        ->test(EstadoDeEstudiantes::class)
        ->set('situacion', EstadoDeEstudiantes::LE_FALTA)
        ->assertSee('Carla Semestre Pasado');
});

it('busca estudiantes por nombre y correo', function (): void {
    User::factory()->estudiante()->create(['nombre' => 'Ana Gómez', 'email' => 'ana@u.edu.co']);
    User::factory()->estudiante()->create(['nombre' => 'Beto Ruiz', 'email' => 'beto@u.edu.co']);

    Livewire::actingAs($this->coordinadora)
        ->test(EstadoDeEstudiantes::class)
        ->set('busqueda', 'gómez')
        ->assertSee('Ana Gómez')
        ->assertDontSee('Beto Ruiz')
        ->set('busqueda', 'beto@u.edu.co')
        ->assertSee('Beto Ruiz')
        ->assertDontSee('Ana Gómez');
});

it('solo lista estudiantes, no usuarios de otros roles', function (): void {
    Livewire::actingAs($this->coordinadora)
        ->test(EstadoDeEstudiantes::class)
        ->assertSee($this->estudiante->nombre)
        ->assertDontSee($this->administrativo->nombre);
});

it('no genera consultas N+1 al recorrer el estado de los estudiantes', function (): void {
    User::factory()->count(6)->estudiante()->create()
        ->each(fn (User $u) => ConsentimientoEstudiante::factory()->verificado()->delPeriodo('2026-2')->create([
            'estudiante_id' => $u->id,
        ]));

    Model::preventLazyLoading();

    Livewire::actingAs($this->coordinadora)->test(EstadoDeEstudiantes::class)->assertOk();
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
        ->and(ConsentimientoPlantilla::where('activo', true)->count())->toBe(1);
});

it('no deja a nadie más cargar la plantilla', function (string $quien): void {
    $this->actingAs($this->$quien)->get(route('panel.plantillas-consentimiento'))->assertForbidden();
})->with(['coordinadora', 'administrativo', 'estudiante']);

it('marca cuál plantilla está vigente en el historial', function (): void {
    ConsentimientoPlantilla::factory()->inactiva()->create(['version' => '1.0']);
    ConsentimientoPlantilla::factory()->create(['version' => '2.0']);

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
    $entrega = ConsentimientoEstudiante::factory()->cargado()->delPeriodo('2026-2')->create([
        'estudiante_id' => $otro->id,
    ]);

    $this->actingAs($this->estudiante)
        ->get(route('panel.consentimientos.firmado', $entrega))
        ->assertForbidden();
});

it('deja al estudiante descargar el suyo', function (): void {
    Storage::disk('local')->put('consentimientos/firmados/mio.pdf', '%PDF-1.4 contenido');
    $entrega = ConsentimientoEstudiante::factory()->cargado()->delPeriodo('2026-2')->create([
        'estudiante_id' => $this->estudiante->id,
        'archivo_firmado_path' => 'consentimientos/firmados/mio.pdf',
    ]);

    $this->actingAs($this->estudiante)
        ->get(route('panel.consentimientos.firmado', $entrega))
        ->assertOk()
        ->assertDownload();
});

it('deja descargar el documento a quien lo verifica', function (string $quien): void {
    Storage::disk('local')->put('consentimientos/firmados/x.pdf', '%PDF-1.4');
    $entrega = ConsentimientoEstudiante::factory()->cargado()->delPeriodo('2026-2')->create([
        'estudiante_id' => $this->estudiante->id,
        'archivo_firmado_path' => 'consentimientos/firmados/x.pdf',
    ]);

    $this->actingAs($this->$quien)
        ->get(route('panel.consentimientos.firmado', $entrega))
        ->assertOk();
})->with(['administrativo', 'coordinadora', 'admin']);

it('no deja a un docente descargar un documento firmado', function (): void {
    Storage::disk('local')->put('consentimientos/firmados/x.pdf', '%PDF-1.4');
    $entrega = ConsentimientoEstudiante::factory()->cargado()->delPeriodo('2026-2')->create([
        'estudiante_id' => $this->estudiante->id,
        'archivo_firmado_path' => 'consentimientos/firmados/x.pdf',
    ]);

    $usuario = User::factory()->docente()->create();

    $this->actingAs($usuario->fresh())
        ->get(route('panel.consentimientos.firmado', $entrega))
        ->assertForbidden();
});

it('no sirve el archivo firmado por enlace directo al disco', function (): void {
    // El disco es privado: la ruta pública de Laravel no lo entrega sin
    // firma, y de todas formas no pasa por la Policy.
    Storage::disk('local')->put('consentimientos/firmados/secreto.pdf', '%PDF-1.4');

    $this->actingAs($this->estudiante)
        ->get('/storage/consentimientos/firmados/secreto.pdf')
        ->assertForbidden();
});

it('no deja a un invitado tocar ninguna descarga', function (): void {
    $entrega = ConsentimientoEstudiante::factory()->cargado()->create();

    $this->get(route('panel.consentimientos.firmado', $entrega))->assertRedirect('/');
    $this->get(route('panel.consentimientos.plantilla'))->assertRedirect('/');
});

it('no ofrece verificar ni devolver un consentimiento que no está cargado', function (EstadoConsentimiento $estado): void {
    ConsentimientoEstudiante::factory()->delPeriodo('2026-2')->create([
        'estudiante_id' => $this->estudiante->id,
        'estado' => $estado,
    ]);

    Livewire::actingAs($this->coordinadora)
        ->test(BandejaVerificacion::class)
        ->set('estado', $estado->value)
        ->assertSee($this->estudiante->nombre)
        ->assertDontSee('>Verificar<', escape: false)
        ->assertDontSee('>Devolver<', escape: false);
})->with([EstadoConsentimiento::Pendiente, EstadoConsentimiento::Verificado]);

it('ofrece verificar y devolver solo cuando está cargado', function (): void {
    ConsentimientoEstudiante::factory()->cargado()->delPeriodo('2026-2')->create([
        'estudiante_id' => $this->estudiante->id,
    ]);

    Livewire::actingAs($this->coordinadora)
        ->test(BandejaVerificacion::class)
        ->assertSee('Verificar')
        ->assertSee('Devolver');
});
