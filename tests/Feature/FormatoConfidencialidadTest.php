<?php

declare(strict_types=1);

use App\Enums\EstadoFormatoConfidencialidad;
use App\Enums\Rol;
use App\Exceptions\FormatoConfidencialidadInvalido;
use App\Models\FormatoConfidencialidad;
use App\Models\PlantillaConfidencialidad;
use App\Models\User;
use App\Services\ConfidencialidadService;
use Database\Seeders\RolSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    $this->seed(RolSeeder::class);
    $this->servicio = app(ConfidencialidadService::class);
    $this->admin = User::factory()->admin()->create();
    $this->coordinadora = User::factory()->coordinador()->create();
    $this->administrativo = User::factory()->administrativo()->create();
    $this->estudiante = User::factory()->estudiante()->create();
});

/** Un PDF de prueba del tamaño pedido, en KB. */
function pdfDePrueba(int $kilobytes = 200, string $nombre = 'formato-confidencialidad.pdf'): UploadedFile
{
    return UploadedFile::fake()->create($nombre, $kilobytes, 'application/pdf');
}

/** Deja una plantilla activa y devuelve la entrega cargada del estudiante. */
function entregaCargada(User $estudiante): FormatoConfidencialidad
{
    PlantillaConfidencialidad::factory()->create();

    return app(ConfidencialidadService::class)->registrarEntrega($estudiante, pdfDePrueba());
}

// ---------------------------------------------------------------------
// Periodo académico
// ---------------------------------------------------------------------

it('deriva el periodo vigente del calendario', function (string $hoy, string $esperado): void {
    config(['laboratorio.periodo_academico.vigente' => null]);
    $this->travelTo($hoy);

    expect($this->servicio->periodoVigente())->toBe($esperado);
})->with([
    'enero es primer semestre' => ['2026-01-15', '2026-1'],
    'junio sigue siendo primero' => ['2026-06-30', '2026-1'],
    'julio abre el segundo' => ['2026-07-01', '2026-2'],
    'diciembre cierra el segundo' => ['2026-12-20', '2026-2'],
    'cambia de año' => ['2027-03-04', '2027-1'],
]);

it('deja fijar el periodo a mano por encima del calendario', function (): void {
    // Válvula de escape para las semanas de transición entre semestres.
    $this->travelTo('2026-01-15');
    config(['laboratorio.periodo_academico.vigente' => '2025-2']);

    expect($this->servicio->periodoVigente())->toBe('2025-2');
});

// ---------------------------------------------------------------------
// Plantilla (RF51)
// ---------------------------------------------------------------------

it('deja al ADMIN cargar la plantilla y deja solo una activa', function (): void {
    $primera = $this->servicio->cargarPlantilla($this->admin, pdfDePrueba(), 'Formato 2026', '1.0');
    $segunda = $this->servicio->cargarPlantilla($this->admin, pdfDePrueba(), 'Formato 2026', '2.0');

    expect($segunda->activo)->toBeTrue()
        ->and($primera->fresh()->activo)->toBeFalse()
        ->and(PlantillaConfidencialidad::activas()->count())->toBe(1)
        ->and($this->servicio->plantillaVigente()->id)->toBe($segunda->id);

    Storage::disk('local')->assertExists($segunda->archivo_path);
});

it('no deja a nadie más cargar la plantilla', function (string $quien): void {
    expect(fn () => $this->servicio->cargarPlantilla($this->$quien, pdfDePrueba(), 'Formato', '1.0'))
        ->toThrow(AuthorizationException::class);
})->with(['coordinadora', 'administrativo', 'estudiante']);

it('avisa cuando no hay plantilla activa que firmar', function (): void {
    PlantillaConfidencialidad::factory()->inactiva()->create();

    expect(fn () => $this->servicio->registrarEntrega($this->estudiante, pdfDePrueba()))
        ->toThrow(FormatoConfidencialidadInvalido::class, 'No hay plantilla de confidencialidad activa');
});

// ---------------------------------------------------------------------
// Entrega del estudiante (RF52)
// ---------------------------------------------------------------------

it('registra la entrega del estudiante contra la plantilla activa y el periodo vigente', function (): void {
    config(['laboratorio.periodo_academico.vigente' => '2026-2']);
    $plantilla = PlantillaConfidencialidad::factory()->create();

    $entrega = $this->servicio->registrarEntrega($this->estudiante, pdfDePrueba());

    expect($entrega->firmante_id)->toBe($this->estudiante->id)
        ->and($entrega->plantilla_id)->toBe($plantilla->id)
        ->and($entrega->periodo_academico)->toBe('2026-2')
        ->and($entrega->estado)->toBe(EstadoFormatoConfidencialidad::Cargado);

    Storage::disk('local')->assertExists($entrega->archivo_firmado_path);
});

it('guarda el archivo firmado fuera del almacenamiento público', function (): void {
    $entrega = entregaCargada($this->estudiante);

    // El disco "public" es el único expuesto por enlace directo; el firmado
    // no puede estar ahí porque lleva datos personales (RNF07).
    expect(config('laboratorio.confidencialidad.disco'))->toBe('local')
        ->and(Storage::disk('public')->exists($entrega->archivo_firmado_path))->toBeFalse();
});

it('no acepta un archivo que no sea PDF', function (): void {
    PlantillaConfidencialidad::factory()->create();
    $foto = UploadedFile::fake()->create('firma.png', 100, 'image/png');

    expect(fn () => $this->servicio->registrarEntrega($this->estudiante, $foto))
        ->toThrow(FormatoConfidencialidadInvalido::class, 'debe entregarse en PDF');
});

it('no acepta un PDF que pase del tamaño máximo', function (): void {
    PlantillaConfidencialidad::factory()->create();
    $maximoKb = (int) config('laboratorio.confidencialidad.tamano_maximo_kb');

    expect(fn () => $this->servicio->registrarEntrega($this->estudiante, pdfDePrueba($maximoKb + 1)))
        ->toThrow(FormatoConfidencialidadInvalido::class, 'supera el máximo');
});

it('reemplaza la entrega del mismo periodo en vez de crear una segunda', function (): void {
    config(['laboratorio.periodo_academico.vigente' => '2026-2']);
    $primera = entregaCargada($this->estudiante);
    $rutaVieja = $primera->archivo_firmado_path;

    $segunda = $this->servicio->registrarEntrega($this->estudiante, pdfDePrueba());

    expect($segunda->id)->toBe($primera->id)
        ->and(FormatoConfidencialidad::where('firmante_id', $this->estudiante->id)->count())->toBe(1)
        ->and($segunda->archivo_firmado_path)->not->toBe($rutaVieja);

    Storage::disk('local')->assertMissing($rutaVieja);
});

// ---------------------------------------------------------------------
// Verificación: la ejerce el administrativo; coordinación y ADMIN supervisan
// ---------------------------------------------------------------------

it('deja verificar el formato a quien tiene el permiso', function (string $quien): void {
    $entrega = entregaCargada($this->estudiante);

    $verificada = $this->servicio->verificar($entrega, $this->$quien);

    expect($verificada->estado)->toBe(EstadoFormatoConfidencialidad::Verificado)
        ->and($verificada->verificado_por)->toBe($this->$quien->id)
        ->and($verificada->verificado_at)->not->toBeNull();
})->with(['administrativo', 'coordinadora', 'admin']);

it('no deja verificar ni rechazar al docente ni al propio estudiante', function (string $rol): void {
    $entrega = entregaCargada($this->estudiante);
    $usuario = User::factory()->create();
    $usuario->assignRole($rol);

    expect(fn () => $this->servicio->verificar($entrega, $usuario))->toThrow(AuthorizationException::class)
        ->and(fn () => $this->servicio->rechazar($entrega, $usuario, 'no'))->toThrow(AuthorizationException::class);
})->with([Rol::Docente->value, Rol::Estudiante->value]);

it('devuelve la entrega a pendiente al rechazarla y borra el archivo', function (): void {
    $entrega = entregaCargada($this->estudiante);
    $ruta = $entrega->archivo_firmado_path;

    $rechazada = $this->servicio->rechazar($entrega, $this->coordinadora, 'La firma no coincide con el documento.');

    expect($rechazada->estado)->toBe(EstadoFormatoConfidencialidad::Pendiente)
        ->and($rechazada->motivo_rechazo)->toBe('La firma no coincide con el documento.')
        ->and($rechazada->archivo_firmado_path)->toBeNull()
        ->and($rechazada->verificado_por)->toBeNull()
        ->and($rechazada->verificado_at)->toBeNull();

    Storage::disk('local')->assertMissing($ruta);
});

it('deja al estudiante volver a subir el formato rechazado', function (): void {
    $entrega = entregaCargada($this->estudiante);
    $this->servicio->rechazar($entrega, $this->coordinadora, 'Falta la página 2.');

    $reenviada = $this->servicio->registrarEntrega($this->estudiante, pdfDePrueba());

    expect($reenviada->id)->toBe($entrega->id)
        ->and($reenviada->estado)->toBe(EstadoFormatoConfidencialidad::Cargado)
        ->and($reenviada->motivo_rechazo)->toBeNull();
});

it('solo verifica o rechaza formatos cargados', function (string $accion): void {
    $entrega = FormatoConfidencialidad::factory()->verificado()->create([
        'firmante_id' => $this->estudiante->id,
    ]);

    expect(fn () => $this->servicio->$accion($entrega, $this->coordinadora))
        ->toThrow(FormatoConfidencialidadInvalido::class, 'está "verificado"');
})->with(['verificar', 'rechazar']);

// ---------------------------------------------------------------------
// Vigencia semestral (RF52) y bloqueo de prácticas (RF53)
// ---------------------------------------------------------------------

it('reconoce como vigente el formato verificado de este periodo', function (): void {
    config(['laboratorio.periodo_academico.vigente' => '2026-2']);
    $entrega = entregaCargada($this->estudiante);
    $this->servicio->verificar($entrega, $this->coordinadora);

    expect($this->servicio->tieneFormatoVigente($this->estudiante))->toBeTrue()
        ->and($this->servicio->puedeParticiparEnPracticas($this->estudiante))->toBeTrue();
});

it('no da por vigente lo entregado el semestre pasado', function (): void {
    FormatoConfidencialidad::factory()->verificado()->delPeriodo('2026-1')->create([
        'firmante_id' => $this->estudiante->id,
    ]);
    config(['laboratorio.periodo_academico.vigente' => '2026-2']);

    expect($this->servicio->tieneFormatoVigente($this->estudiante))->toBeFalse()
        ->and($this->servicio->puedeParticiparEnPracticas($this->estudiante))->toBeFalse();
});

it('no da por vigente un formato entregado pero sin verificar', function (): void {
    config(['laboratorio.periodo_academico.vigente' => '2026-2']);
    entregaCargada($this->estudiante);

    expect($this->servicio->tieneFormatoVigente($this->estudiante))->toBeFalse();
});

it('no da por vigente el formato de otro estudiante', function (): void {
    config(['laboratorio.periodo_academico.vigente' => '2026-2']);
    $otro = User::factory()->estudiante()->create();
    $entrega = entregaCargada($otro);
    $this->servicio->verificar($entrega, $this->coordinadora);

    expect($this->servicio->tieneFormatoVigente($this->estudiante))->toBeFalse()
        ->and($this->servicio->tieneFormatoVigente($otro))->toBeTrue();
});

it('impide dos entregas del mismo estudiante en el mismo periodo', function (): void {
    FormatoConfidencialidad::factory()->delPeriodo('2026-2')->create([
        'firmante_id' => $this->estudiante->id,
    ]);

    expect(fn () => FormatoConfidencialidad::factory()->delPeriodo('2026-2')->create([
        'firmante_id' => $this->estudiante->id,
    ]))->toThrow(QueryException::class);
});

it('deja al mismo estudiante entregar en periodos distintos', function (): void {
    FormatoConfidencialidad::factory()->delPeriodo('2026-1')->create(['firmante_id' => $this->estudiante->id]);
    FormatoConfidencialidad::factory()->delPeriodo('2026-2')->create(['firmante_id' => $this->estudiante->id]);

    expect(FormatoConfidencialidad::where('firmante_id', $this->estudiante->id)->count())->toBe(2);
});

// ---------------------------------------------------------------------
// Quién firma el formato (RF51-RF52)
// ---------------------------------------------------------------------

it('deja entregar el formato a quien entra a la práctica', function (string $quien): void {
    config(['laboratorio.periodo_academico.vigente' => '2026-2']);
    $firmante = User::factory()->$quien()->create();

    expect($firmante->can('create', FormatoConfidencialidad::class))->toBeTrue()
        ->and(entregaCargada($firmante)->firmante_id)->toBe($firmante->id);
})->with(['estudiante', 'docente']);

it('no deja entregar el formato a quien no entra a la práctica', function (string $quien): void {
    expect($this->$quien->can('create', FormatoConfidencialidad::class))->toBeFalse();
})->with(['administrativo', 'coordinadora', 'admin']);

it('habilita el ingreso de un docente con su formato verificado', function (): void {
    config(['laboratorio.periodo_academico.vigente' => '2026-2']);
    $docente = User::factory()->docente()->create();
    $this->servicio->verificar(entregaCargada($docente), $this->administrativo);

    expect($this->servicio->puedeParticiparEnPracticas($docente))->toBeTrue()
        ->and($this->servicio->tieneFormatoVigente($docente))->toBeTrue();
});

it('habilita el ingreso de un docente que entregó en físico', function (): void {
    config(['laboratorio.periodo_academico.vigente' => '2026-2']);
    PlantillaConfidencialidad::factory()->create();
    $docente = User::factory()->docente()->create();

    $this->servicio->registrarEntregaFisica($docente, $this->administrativo);

    // Entra, pero el trámite sigue abierto: son dos preguntas distintas (RF53).
    expect($this->servicio->puedeParticiparEnPracticas($docente))->toBeTrue()
        ->and($this->servicio->tieneFormatoVigente($docente))->toBeFalse();
});

it('no deja a nadie entregar el formato en nombre de otro', function (): void {
    config(['laboratorio.periodo_academico.vigente' => '2026-2']);
    $docente = User::factory()->docente()->create();
    $entrega = entregaCargada($docente);

    // El dueño lo reemplaza; quien verifica no, por más permiso que tenga.
    expect($docente->can('update', $entrega))->toBeTrue()
        ->and($this->administrativo->can('update', $entrega))->toBeFalse()
        ->and($this->estudiante->can('update', $entrega))->toBeFalse();
});

it('lista a estudiantes y docentes juntos en el estado de firmantes', function (): void {
    config(['laboratorio.periodo_academico.vigente' => '2026-2']);
    $docente = User::factory()->docente()->create();

    $firmantes = $this->servicio->estadoDeLosFirmantes('2026-2')->pluck('id');

    expect($firmantes)->toContain($this->estudiante->id)
        ->toContain($docente->id)
        ->not->toContain($this->administrativo->id);
});

it('busca a un firmante por su código institucional', function (): void {
    config(['laboratorio.periodo_academico.vigente' => '2026-2']);
    $buscado = User::factory()->estudiante()->create(['codigo_institucional' => 'EST-4321']);
    User::factory()->estudiante()->create(['codigo_institucional' => 'EST-0000']);

    $encontrados = $this->servicio->estadoDeLosFirmantes('2026-2', busqueda: 'EST-4321');

    expect($encontrados->pluck('id')->all())->toBe([$buscado->id]);
});
