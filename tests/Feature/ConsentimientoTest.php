<?php

declare(strict_types=1);

use App\Enums\EstadoConsentimiento;
use App\Enums\Rol;
use App\Exceptions\ConsentimientoInvalido;
use App\Models\ConsentimientoEstudiante;
use App\Models\ConsentimientoPlantilla;
use App\Models\User;
use App\Services\ConsentimientoService;
use Database\Seeders\RolSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    $this->seed(RolSeeder::class);
    $this->servicio = app(ConsentimientoService::class);
    $this->admin = User::factory()->admin()->create();
    $this->coordinadora = User::factory()->coordinador()->create();
    $this->administrativo = User::factory()->administrativo()->create();
    $this->estudiante = User::factory()->estudiante()->create();
});

/** Un PDF de prueba del tamaño pedido, en KB. */
function pdfDePrueba(int $kilobytes = 200, string $nombre = 'consentimiento.pdf'): UploadedFile
{
    return UploadedFile::fake()->create($nombre, $kilobytes, 'application/pdf');
}

/** Deja una plantilla activa y devuelve la entrega cargada del estudiante. */
function entregaCargada(User $estudiante): ConsentimientoEstudiante
{
    ConsentimientoPlantilla::factory()->create();

    return app(ConsentimientoService::class)->registrarEntrega($estudiante, pdfDePrueba());
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
    $primera = $this->servicio->cargarPlantilla($this->admin, pdfDePrueba(), 'Consentimiento 2026', '1.0');
    $segunda = $this->servicio->cargarPlantilla($this->admin, pdfDePrueba(), 'Consentimiento 2026', '2.0');

    expect($segunda->activo)->toBeTrue()
        ->and($primera->fresh()->activo)->toBeFalse()
        ->and(ConsentimientoPlantilla::activas()->count())->toBe(1)
        ->and($this->servicio->plantillaVigente()->id)->toBe($segunda->id);

    Storage::disk('local')->assertExists($segunda->archivo_path);
});

it('no deja a nadie más cargar la plantilla', function (string $quien): void {
    expect(fn () => $this->servicio->cargarPlantilla($this->$quien, pdfDePrueba(), 'Consentimiento', '1.0'))
        ->toThrow(AuthorizationException::class);
})->with(['coordinadora', 'administrativo', 'estudiante']);

it('avisa cuando no hay plantilla activa que firmar', function (): void {
    ConsentimientoPlantilla::factory()->inactiva()->create();

    expect(fn () => $this->servicio->registrarEntrega($this->estudiante, pdfDePrueba()))
        ->toThrow(ConsentimientoInvalido::class, 'No hay plantilla de consentimiento activa');
});

// ---------------------------------------------------------------------
// Entrega del estudiante (RF52)
// ---------------------------------------------------------------------

it('registra la entrega del estudiante contra la plantilla activa y el periodo vigente', function (): void {
    config(['laboratorio.periodo_academico.vigente' => '2026-2']);
    $plantilla = ConsentimientoPlantilla::factory()->create();

    $entrega = $this->servicio->registrarEntrega($this->estudiante, pdfDePrueba());

    expect($entrega->estudiante_id)->toBe($this->estudiante->id)
        ->and($entrega->plantilla_id)->toBe($plantilla->id)
        ->and($entrega->periodo_academico)->toBe('2026-2')
        ->and($entrega->estado)->toBe(EstadoConsentimiento::Cargado);

    Storage::disk('local')->assertExists($entrega->archivo_firmado_path);
});

it('guarda el archivo firmado fuera del almacenamiento público', function (): void {
    $entrega = entregaCargada($this->estudiante);

    // El disco "public" es el único expuesto por enlace directo; el firmado
    // no puede estar ahí porque lleva datos personales (RNF07).
    expect(config('laboratorio.consentimiento.disco'))->toBe('local')
        ->and(Storage::disk('public')->exists($entrega->archivo_firmado_path))->toBeFalse();
});

it('no acepta un archivo que no sea PDF', function (): void {
    ConsentimientoPlantilla::factory()->create();
    $foto = UploadedFile::fake()->create('firma.png', 100, 'image/png');

    expect(fn () => $this->servicio->registrarEntrega($this->estudiante, $foto))
        ->toThrow(ConsentimientoInvalido::class, 'debe entregarse en PDF');
});

it('no acepta un PDF que pase del tamaño máximo', function (): void {
    ConsentimientoPlantilla::factory()->create();
    $maximoKb = (int) config('laboratorio.consentimiento.tamano_maximo_kb');

    expect(fn () => $this->servicio->registrarEntrega($this->estudiante, pdfDePrueba($maximoKb + 1)))
        ->toThrow(ConsentimientoInvalido::class, 'supera el máximo');
});

it('reemplaza la entrega del mismo periodo en vez de crear una segunda', function (): void {
    config(['laboratorio.periodo_academico.vigente' => '2026-2']);
    $primera = entregaCargada($this->estudiante);
    $rutaVieja = $primera->archivo_firmado_path;

    $segunda = $this->servicio->registrarEntrega($this->estudiante, pdfDePrueba());

    expect($segunda->id)->toBe($primera->id)
        ->and(ConsentimientoEstudiante::where('estudiante_id', $this->estudiante->id)->count())->toBe(1)
        ->and($segunda->archivo_firmado_path)->not->toBe($rutaVieja);

    Storage::disk('local')->assertMissing($rutaVieja);
});

// ---------------------------------------------------------------------
// Verificación: coordinador y ADMIN, nunca el administrativo
// ---------------------------------------------------------------------

it('deja verificar el consentimiento al coordinador y al ADMIN', function (string $quien): void {
    $entrega = entregaCargada($this->estudiante);

    $verificada = $this->servicio->verificar($entrega, $this->$quien);

    expect($verificada->estado)->toBe(EstadoConsentimiento::Verificado)
        ->and($verificada->verificado_por)->toBe($this->$quien->id)
        ->and($verificada->verificado_at)->not->toBeNull();
})->with(['coordinadora', 'admin']);

it('no deja al administrativo verificar consentimientos', function (): void {
    // Única función operativa donde el administrativo no acompaña al
    // coordinador (§6.1 del documento de arquitectura).
    $entrega = entregaCargada($this->estudiante);

    expect(fn () => $this->servicio->verificar($entrega, $this->administrativo))
        ->toThrow(AuthorizationException::class);

    expect($entrega->fresh()->estado)->toBe(EstadoConsentimiento::Cargado);
});

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

    expect($rechazada->estado)->toBe(EstadoConsentimiento::Pendiente)
        ->and($rechazada->motivo_rechazo)->toBe('La firma no coincide con el documento.')
        ->and($rechazada->archivo_firmado_path)->toBeNull()
        ->and($rechazada->verificado_por)->toBeNull()
        ->and($rechazada->verificado_at)->toBeNull();

    Storage::disk('local')->assertMissing($ruta);
});

it('deja al estudiante volver a subir el consentimiento rechazado', function (): void {
    $entrega = entregaCargada($this->estudiante);
    $this->servicio->rechazar($entrega, $this->coordinadora, 'Falta la página 2.');

    $reenviada = $this->servicio->registrarEntrega($this->estudiante, pdfDePrueba());

    expect($reenviada->id)->toBe($entrega->id)
        ->and($reenviada->estado)->toBe(EstadoConsentimiento::Cargado)
        ->and($reenviada->motivo_rechazo)->toBeNull();
});

it('solo verifica o rechaza consentimientos cargados', function (string $accion): void {
    $entrega = ConsentimientoEstudiante::factory()->verificado()->create([
        'estudiante_id' => $this->estudiante->id,
    ]);

    expect(fn () => $this->servicio->$accion($entrega, $this->coordinadora))
        ->toThrow(ConsentimientoInvalido::class, 'está "verificado"');
})->with(['verificar', 'rechazar']);

// ---------------------------------------------------------------------
// Vigencia semestral (RF52) y bloqueo de prácticas (RF53)
// ---------------------------------------------------------------------

it('reconoce como vigente el consentimiento verificado de este periodo', function (): void {
    config(['laboratorio.periodo_academico.vigente' => '2026-2']);
    $entrega = entregaCargada($this->estudiante);
    $this->servicio->verificar($entrega, $this->coordinadora);

    expect($this->servicio->tieneConsentimientoVigente($this->estudiante))->toBeTrue()
        ->and($this->servicio->puedeParticiparEnPracticas($this->estudiante))->toBeTrue();
});

it('no da por vigente lo entregado el semestre pasado', function (): void {
    ConsentimientoEstudiante::factory()->verificado()->delPeriodo('2026-1')->create([
        'estudiante_id' => $this->estudiante->id,
    ]);
    config(['laboratorio.periodo_academico.vigente' => '2026-2']);

    expect($this->servicio->tieneConsentimientoVigente($this->estudiante))->toBeFalse()
        ->and($this->servicio->puedeParticiparEnPracticas($this->estudiante))->toBeFalse();
});

it('no da por vigente un consentimiento entregado pero sin verificar', function (): void {
    config(['laboratorio.periodo_academico.vigente' => '2026-2']);
    entregaCargada($this->estudiante);

    expect($this->servicio->tieneConsentimientoVigente($this->estudiante))->toBeFalse();
});

it('no da por vigente el consentimiento de otro estudiante', function (): void {
    config(['laboratorio.periodo_academico.vigente' => '2026-2']);
    $otro = User::factory()->estudiante()->create();
    $entrega = entregaCargada($otro);
    $this->servicio->verificar($entrega, $this->coordinadora);

    expect($this->servicio->tieneConsentimientoVigente($this->estudiante))->toBeFalse()
        ->and($this->servicio->tieneConsentimientoVigente($otro))->toBeTrue();
});

it('impide dos entregas del mismo estudiante en el mismo periodo', function (): void {
    ConsentimientoEstudiante::factory()->delPeriodo('2026-2')->create([
        'estudiante_id' => $this->estudiante->id,
    ]);

    expect(fn () => ConsentimientoEstudiante::factory()->delPeriodo('2026-2')->create([
        'estudiante_id' => $this->estudiante->id,
    ]))->toThrow(QueryException::class);
});

it('deja al mismo estudiante entregar en periodos distintos', function (): void {
    ConsentimientoEstudiante::factory()->delPeriodo('2026-1')->create(['estudiante_id' => $this->estudiante->id]);
    ConsentimientoEstudiante::factory()->delPeriodo('2026-2')->create(['estudiante_id' => $this->estudiante->id]);

    expect(ConsentimientoEstudiante::where('estudiante_id', $this->estudiante->id)->count())->toBe(2);
});
