<?php

declare(strict_types=1);

use App\Enums\AccionAuditada;
use App\Livewire\Retencion\DepuracionDeDatos;
use App\Models\Evaluacion;
use App\Models\EvaluacionEstudiante;
use App\Models\FormatoConfidencialidad;
use App\Models\PeriodoAcademico;
use App\Models\RegistroDeBitacora;
use App\Models\User;
use App\Services\RetencionDeDatosService;
use Database\Seeders\RolSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Retención de dos semestres (decisión del cliente del 2026-11-08): los
 * formatos firmados, las evaluaciones y la bitácora se conservan dos
 * periodos académicos, el vigente y el anterior. Lo que es más viejo que el
 * comienzo del anterior se puede depurar, siempre por el ADMIN y después de
 * descargar los formatos: nada se borra solo. Del formato se borra el PDF;
 * la fila, con quién lo firmó y quién lo verificó, se queda.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    Storage::fake(config('laboratorio.confidencialidad.disco'));
    $this->servicio = app(RetencionDeDatosService::class);
    $this->admin = User::factory()->admin()->create();
});

/** Un periodo abierto hace tantos meses; solo el último sigue abierto. */
function periodoAbiertoHace(string $nombre, int $meses, bool $sigueAbierto = false): PeriodoAcademico
{
    $periodo = $sigueAbierto ? PeriodoAcademico::factory() : PeriodoAcademico::factory()->cerrado();

    return $periodo->create(['nombre' => $nombre, 'abierto_at' => now()->subMonths($meses)]);
}

/** Tres semestres: el vigente, el anterior y uno más viejo que se puede depurar. */
function tresSemestres(): Carbon
{
    periodoAbiertoHace('2025-2', 13);
    $anterior = periodoAbiertoHace('2026-1', 7);
    periodoAbiertoHace('2026-2', 1, sigueAbierto: true);

    return $anterior->abierto_at;
}

/** Una evaluación de una sesión en esa fecha, con un estudiante. */
function evaluacionDelDia(Carbon $fecha): Evaluacion
{
    $evaluacion = Evaluacion::factory()->create();
    $evaluacion->solicitud()->update(['fecha' => $fecha->toDateString()]);
    EvaluacionEstudiante::factory()->create(['evaluacion_id' => $evaluacion->id]);

    return $evaluacion;
}

/** Una entrada de la bitácora escrita en ese momento. */
function entradaDeBitacoraDel(Carbon $momento): RegistroDeBitacora
{
    $entrada = RegistroDeBitacora::create([
        'accion' => AccionAuditada::SolicitudAprobada,
        'user_id' => test()->admin->id,
        'descripcion' => 'Aprobó una solicitud.',
    ]);
    DB::table('bitacora')->where('id', $entrada->id)->update(['created_at' => $momento]);

    return $entrada;
}

/** Un formato firmado de ese periodo, con su PDF en el disco. */
function formatoFirmadoDel(string $periodo): FormatoConfidencialidad
{
    $formato = FormatoConfidencialidad::factory()->verificado()->create(['periodo_academico' => $periodo]);
    Storage::disk(config('laboratorio.confidencialidad.disco'))->put($formato->archivo_firmado_path, '%PDF-1.4 firmado');

    return $formato;
}

it('conserva el periodo vigente y el anterior, y cuenta lo que es más viejo', function (): void {
    $corte = tresSemestres();
    evaluacionDelDia($corte->copy()->subDays(20));
    evaluacionDelDia($corte->copy()->addDays(20));
    entradaDeBitacoraDel($corte->copy()->subDays(20));
    entradaDeBitacoraDel($corte->copy()->addDays(20));
    formatoFirmadoDel('2025-2');
    formatoFirmadoDel('2026-1');

    $pendiente = $this->servicio->porDepurar();

    expect($pendiente->periodosConservados)->toBe(['2026-2', '2026-1'])
        ->and($pendiente->evaluaciones)->toBe(1)
        ->and($pendiente->entradasDeBitacora)->toBe(1)
        ->and($pendiente->formatos)->toBe(1)
        ->and($pendiente->hayAlgo())->toBeTrue();
});

it('depura lo de más de dos semestres y deja todo lo reciente', function (): void {
    $corte = tresSemestres();
    $vieja = evaluacionDelDia($corte->copy()->subDays(20));
    $reciente = evaluacionDelDia($corte->copy()->addDays(20));
    $entradaVieja = entradaDeBitacoraDel($corte->copy()->subDays(20));
    $entradaReciente = entradaDeBitacoraDel($corte->copy()->addDays(20));
    $formatoViejo = formatoFirmadoDel('2025-2');
    $formatoReciente = formatoFirmadoDel('2026-1');
    $documentoViejo = $formatoViejo->archivo_firmado_path;

    $this->servicio->depurar($this->admin);

    $disco = Storage::disk(config('laboratorio.confidencialidad.disco'));

    expect(Evaluacion::query()->pluck('id')->all())->toBe([$reciente->id])
        ->and(EvaluacionEstudiante::query()->where('evaluacion_id', $vieja->id)->exists())->toBeFalse()
        ->and(RegistroDeBitacora::query()->whereKey($entradaVieja->id)->exists())->toBeFalse()
        ->and(RegistroDeBitacora::query()->whereKey($entradaReciente->id)->exists())->toBeTrue()
        // Del formato viejo se va el PDF; la fila se queda.
        ->and($formatoViejo->fresh()->archivo_firmado_path)->toBeNull()
        ->and($disco->exists($documentoViejo))->toBeFalse()
        ->and($disco->exists($formatoReciente->archivo_firmado_path))->toBeTrue();

    $rastro = RegistroDeBitacora::query()->where('accion', AccionAuditada::DatosDepurados)->get();

    expect($rastro)->toHaveCount(1)
        ->and($rastro->first()->descripcion)->toContain('1 evaluación')
        ->and($rastro->first()->descripcion)->toContain('2025-2');
});

it('no depura nada mientras haya un solo periodo', function (): void {
    periodoAbiertoHace('2026-2', 1, sigueAbierto: true);
    entradaDeBitacoraDel(now()->subYears(2));

    expect($this->servicio->porDepurar()->hayAlgo())->toBeFalse()
        ->and(fn () => $this->servicio->depurar($this->admin))->toThrow(DomainException::class)
        ->and(RegistroDeBitacora::query()->count())->toBe(1);
});

it('solo deja depurar al ADMIN', function (): void {
    $corte = tresSemestres();
    $vieja = evaluacionDelDia($corte->copy()->subDays(20));

    expect(fn () => $this->servicio->depurar(User::factory()->coordinador()->create()))->toThrow(AuthorizationException::class)
        ->and($vieja->fresh())->not->toBeNull();
});

it('descarga en un ZIP los formatos que se van a depurar, y queda en la bitácora', function (): void {
    tresSemestres();
    $viejo = formatoFirmadoDel('2025-2');
    formatoFirmadoDel('2026-1');

    $respuesta = $this->actingAs($this->admin)->get(route('panel.depuracion.formatos'))->assertOk();

    $zip = new ZipArchive;
    $zip->open($respuesta->baseResponse->getFile()->getPathname());

    expect($zip->numFiles)->toBe(1)
        ->and($zip->getNameIndex(0))->toStartWith('2025-2/')
        ->and(RegistroDeBitacora::query()->where('accion', AccionAuditada::FormatoDescargado)->count())->toBe(1);

    $zip->close();
    expect($viejo->fresh()->archivo_firmado_path)->not->toBeNull();
});

it('pide confirmar que ya se descargaron los formatos antes de depurar', function (): void {
    $corte = tresSemestres();
    formatoFirmadoDel('2025-2');
    evaluacionDelDia($corte->copy()->subDays(20));

    Livewire::actingAs($this->admin)
        ->test(DepuracionDeDatos::class)
        ->assertSee('2025-2')
        ->call('depurar')
        ->assertHasErrors('descargoLosFormatos')
        ->set('descargoLosFormatos', true)
        ->call('depurar')
        ->assertHasNoErrors();

    expect(Evaluacion::query()->count())->toBe(0);
});

it('avisa al ADMIN en «Hoy» cuando hay datos de más de dos semestres', function (): void {
    $corte = tresSemestres();
    evaluacionDelDia($corte->copy()->subDays(20));

    $this->actingAs($this->admin)->get(route('panel.inicio'))->assertOk()->assertSee('Datos de más de dos semestres');
});

it('no deja entrar a la depuración a nadie más que al ADMIN', function (string $rol): void {
    $this->actingAs(User::factory()->{$rol}()->create())->get(route('panel.depuracion'))->assertForbidden();
    $this->get(route('panel.depuracion.formatos'))->assertForbidden();
})->with(['coordinador', 'administrativo', 'docente']);
