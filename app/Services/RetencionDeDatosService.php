<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccionAuditada;
use App\Exceptions\RetencionInvalida;
use App\Models\Evaluacion;
use App\Models\FormatoConfidencialidad;
use App\Models\PeriodoAcademico;
use App\Models\RegistroDeBitacora;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Retención de datos (decisión del cliente del 2026-11-08; Ley 1581): los
 * formatos firmados, las evaluaciones y la bitácora se conservan dos
 * periodos académicos, el vigente y el anterior (RF75). Lo que es más viejo
 * que el comienzo del anterior se puede depurar.
 *
 * Nada se borra solo. Depura el ADMIN, desde su pantalla, después de
 * descargar en un ZIP los formatos que se van. Del formato se borra el PDF,
 * que es el dato personal; la fila, con quién firmó y quién verificó, se
 * queda. De la evaluación se va todo, con sus estudiantes y sus ítems: quien
 * la repita después de dos semestres, que es raro, vuelve a empezar en el
 * intento 1.
 *
 * Es el único camino que borra entradas de la bitácora, que por lo demás es
 * de solo añadir.
 */
final class RetencionDeDatosService
{
    /** El vigente y el anterior. */
    public const PERIODOS_CONSERVADOS = 2;

    public function __construct(private readonly BitacoraService $bitacora) {}

    public function porDepurar(): DatosPorDepurar
    {
        $conservados = PeriodoAcademico::query()
            ->orderByDesc('abierto_at')
            ->orderByDesc('id')
            ->limit(self::PERIODOS_CONSERVADOS)
            ->get(['nombre', 'abierto_at']);

        /** @var list<string> $nombres */
        $nombres = $conservados->pluck('nombre')->all();

        // Con un solo periodo no se sabe dónde empezó el anterior.
        if ($conservados->count() < self::PERIODOS_CONSERVADOS) {
            return new DatosPorDepurar(corte: null, periodosConservados: $nombres);
        }

        $corte = CarbonImmutable::parse($conservados->last()?->abierto_at);

        /** @var list<string> $periodosDeLosFormatos */
        $periodosDeLosFormatos = $this->formatosQueSeVan($nombres)->distinct()->orderBy('periodo_academico')->pluck('periodo_academico')->all();

        return new DatosPorDepurar(
            corte: $corte,
            periodosConservados: $nombres,
            periodosDeLosFormatos: $periodosDeLosFormatos,
            formatos: $this->formatosQueSeVan($nombres)->count(),
            evaluaciones: $this->evaluacionesQueSeVan($corte)->count(),
            entradasDeBitacora: $this->bitacoraQueSeVa($corte)->count(),
        );
    }

    /**
     * Borra lo que tiene más de dos semestres y lo deja dicho en la bitácora,
     * con las cifras: es lo que responde qué se borró y cuándo.
     */
    public function depurar(User $admin): DatosPorDepurar
    {
        $this->garantizarPermiso($admin);
        $pendiente = $this->porDepurar();
        $corte = $pendiente->corte;

        if (! $corte instanceof CarbonImmutable || ! $pendiente->hayAlgo()) {
            throw RetencionInvalida::nadaQueDepurar();
        }

        DB::transaction(function () use ($pendiente, $corte, $admin): void {
            $formatos = $this->formatosQueSeVan($pendiente->periodosConservados);
            /** @var list<string> $documentos */
            $documentos = $formatos->clone()->pluck('archivo_firmado_path')->all();

            // Los estudiantes y los ítems de cada evaluación se van con ella (cascade).
            $this->evaluacionesQueSeVan($corte)->delete();
            $this->bitacoraQueSeVa($corte)->delete();
            $formatos->update(['archivo_firmado_path' => null]);

            $this->bitacora->registrarSinSujeto(AccionAuditada::DatosDepurados, $admin, sprintf(
                'Depuró lo anterior al %s, que tiene más de dos semestres (se conservan %s): %s, %s y el documento de %s%s.',
                $corte->format('d/m/Y'),
                implode(' y ', $pendiente->periodosConservados),
                $this->cantidad($pendiente->evaluaciones, 'evaluación', 'evaluaciones'),
                $this->cantidad($pendiente->entradasDeBitacora, 'entrada de la bitácora', 'entradas de la bitácora'),
                $this->cantidad($pendiente->formatos, 'formato firmado', 'formatos firmados'),
                $pendiente->periodosDeLosFormatos === [] ? '' : ' del periodo '.implode(', ', $pendiente->periodosDeLosFormatos),
            ));

            // Los PDF, después de confirmar: si la transacción se deshace, siguen ahí.
            DB::afterCommit(static fn () => Storage::disk(config('laboratorio.confidencialidad.disco'))->delete($documentos));
        });

        return $pendiente;
    }

    /**
     * Los formatos que se van, en un ZIP para guardarlos donde el laboratorio
     * guarda sus formatos (su carpeta del Drive) antes de depurar. Sin
     * comprimir: los PDF ya lo están, y así se arma rápido. Devuelve la ruta
     * de un archivo temporal, que quien lo entrega borra al enviarlo. La
     * descarga queda en la bitácora, como la de cada formato (regla 7).
     */
    public function zipDeLosFormatosQueSeVan(User $admin): string
    {
        $this->garantizarPermiso($admin);
        $pendiente = $this->porDepurar();

        if ($pendiente->formatos === 0) {
            throw RetencionInvalida::sinFormatosQueDescargar();
        }

        $disco = Storage::disk(config('laboratorio.confidencialidad.disco'));
        $ruta = (string) tempnam(sys_get_temp_dir(), 'formatos-');
        $zip = new ZipArchive;
        $zip->open($ruta, ZipArchive::OVERWRITE);
        $incluidos = 0;

        $this->formatosQueSeVan($pendiente->periodosConservados)
            ->with('firmante:id,nombre,codigo_institucional')
            ->orderBy('periodo_academico')
            ->orderBy('id')
            ->each(function (FormatoConfidencialidad $formato) use ($zip, $disco, &$incluidos): void {
                $documento = (string) $formato->archivo_firmado_path;

                if (! $disco->exists($documento)) {
                    return;
                }

                $nombre = sprintf(
                    '%s/%s-%s.pdf',
                    $formato->periodo_academico,
                    $formato->firmante->codigo_institucional ?? $formato->firmante_id,
                    Str::slug((string) $formato->firmante?->nombre),
                );
                $zip->addFromString($nombre, (string) $disco->get($documento));
                $zip->setCompressionName($nombre, ZipArchive::CM_STORE);
                $incluidos++;
            });

        $zip->close();

        if ($incluidos === 0) {
            throw RetencionInvalida::sinFormatosQueDescargar();
        }

        $this->bitacora->registrarSinSujeto(AccionAuditada::FormatoDescargado, $admin, sprintf(
            'Descargó en un ZIP %s del periodo %s, para guardarlos antes de depurarlos.',
            $this->cantidad($incluidos, 'formato firmado', 'formatos firmados'),
            implode(', ', $pendiente->periodosDeLosFormatos),
        ));

        return $ruta;
    }

    /**
     * @param  list<string>  $conservados
     * @return Builder<FormatoConfidencialidad>
     */
    private function formatosQueSeVan(array $conservados): Builder
    {
        return FormatoConfidencialidad::query()
            ->whereNotIn('periodo_academico', $conservados)
            ->whereNotNull('archivo_firmado_path');
    }

    /** @return Builder<Evaluacion> */
    private function evaluacionesQueSeVan(CarbonImmutable $corte): Builder
    {
        return Evaluacion::query()->whereHas('solicitud', static fn (Builder $sesion) => $sesion->whereDate('fecha', '<', $corte->toDateString()));
    }

    /** @return Builder<RegistroDeBitacora> */
    private function bitacoraQueSeVa(CarbonImmutable $corte): Builder
    {
        return RegistroDeBitacora::query()->where('created_at', '<', $corte);
    }

    private function cantidad(int $cuantos, string $singular, string $plural): string
    {
        return $cuantos.' '.($cuantos === 1 ? $singular : $plural);
    }

    /**
     * @throws AuthorizationException
     */
    private function garantizarPermiso(User $admin): void
    {
        if (Gate::forUser($admin)->denies('depurarDatos')) {
            throw new AuthorizationException('Solo el administrador de la plataforma depura lo que tiene más de dos semestres.');
        }
    }
}
