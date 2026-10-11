<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoFormatoConfidencialidad;
use App\Enums\EstadoSolicitud;
use App\Models\Evaluacion;
use App\Models\EvaluacionEstudiante;
use App\Models\FormatoConfidencialidad;
use App\Models\Preparacion;
use App\Models\Solicitud;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * La pantalla «Hoy»: lo que espera a cada persona con el rol que tiene
 * puesto. A coordinación, lo que espera su aprobación; al administrativo,
 * los escenarios del día y lo que falta montar; al docente, sus próximas
 * sesiones; al estudiante, sus resultados y su formato.
 *
 * Solo lee. Cada bloque aparece si la Policy de su módulo deja a la persona
 * hacer lo que el bloque propone: aquí no se pregunta por roles, y si
 * mañana cambia quién aprueba, el bloque cambia solo.
 */
final class ResumenDelDiaService
{
    /** Las filas de cada lista; el resto, en su pantalla. */
    public const FILAS_POR_BLOQUE = 5;

    /** Los resultados que se recuerdan al estudiante. */
    public const ULTIMOS_RESULTADOS = 3;

    public function __construct(
        private readonly PreparacionService $preparaciones,
        private readonly ConfidencialidadService $confidencialidad,
        private readonly EvaluacionService $evaluaciones,
    ) {}

    public function para(User $persona, CarbonInterface $hoy): ResumenDelDia
    {
        $docente = $persona->can('create', Solicitud::class);

        return new ResumenDelDia(
            porAprobar: $persona->can('resolverRevisadas', Solicitud::class) ? $this->solicitudesEn(EstadoSolicitud::Revisada) : null,
            montajesDeHoy: $persona->can('viewAny', Preparacion::class) ? $this->preparaciones->tableroDelDia($hoy->toDateString()) : null,
            porRevisar: $persona->can('revisar', Solicitud::class) ? $this->solicitudesEn(EstadoSolicitud::Pendiente) : null,
            proximasSesiones: $docente ? $this->proximasQueDicta($persona, $hoy) : null,
            enTramite: $docente ? $this->enTramiteDe($persona) : null,
            porEvaluar: $persona->can('create', Evaluacion::class) ? $this->porEvaluar($persona) : null,
            ultimosResultados: $persona->can('viewAny', EvaluacionEstudiante::class)
                ? $this->evaluaciones->historialDelEstudiante($persona)->take(self::ULTIMOS_RESULTADOS)
                : null,
            formatosPorVerificar: $persona->can('viewAny', FormatoConfidencialidad::class) ? $this->formatosPorVerificar() : null,
            miFormato: $persona->can('create', FormatoConfidencialidad::class) ? $this->miFormato($persona) : null,
        );
    }

    /**
     * Las solicitudes en un estado, por la fecha de la práctica, como en la
     * bandeja: lo que ocurre antes se atiende antes.
     *
     * @return LengthAwarePaginator<int, Solicitud>
     */
    private function solicitudesEn(EstadoSolicitud $estado): LengthAwarePaginator
    {
        return $this->conLoDeLaFila(Solicitud::query()->enEstado($estado))
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->paginate(self::FILAS_POR_BLOQUE, ['*'], 'pagina', 1);
    }

    /**
     * Las sesiones aprobadas que la persona dicta de hoy en adelante, también
     * por sustitución (RF73).
     *
     * @return Collection<int, Solicitud>
     */
    private function proximasQueDicta(User $docente, CarbonInterface $hoy): Collection
    {
        return $this->conLoDeLaFila(Solicitud::query()->queDicta($docente)->aprobadas())
            ->whereDate('fecha', '>=', $hoy->toDateString())
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->limit(self::FILAS_POR_BLOQUE)
            ->get();
    }

    /**
     * Las que el docente pidió y todavía esperan revisión o aprobación.
     *
     * @return LengthAwarePaginator<int, Solicitud>
     */
    private function enTramiteDe(User $docente): LengthAwarePaginator
    {
        return $this->conLoDeLaFila(Solicitud::query()->delDocente($docente))
            ->whereIn('estado', [EstadoSolicitud::Pendiente, EstadoSolicitud::Revisada])
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->paginate(self::FILAS_POR_BLOQUE, ['*'], 'pagina', 1);
    }

    /** @return Collection<int, Solicitud> */
    private function porEvaluar(User $docente): Collection
    {
        return $this->evaluaciones->sesionesPorEvaluar($docente)
            ->load(['docente:id,nombre', 'docenteQueDicta:id,nombre', 'preparacion.sala']);
    }

    /** Los formatos cargados del periodo vigente que esperan verificación (RF52). */
    private function formatosPorVerificar(): int
    {
        $periodo = $this->confidencialidad->periodoVigente();

        if ($periodo === null) {
            return 0;
        }

        return FormatoConfidencialidad::query()
            ->delPeriodo($periodo)
            ->where('estado', EstadoFormatoConfidencialidad::Cargado)
            ->count();
    }

    private function miFormato(User $firmante): EstadoDeMiFormato
    {
        $periodo = $this->confidencialidad->periodoVigente();

        return new EstadoDeMiFormato($periodo, $this->confidencialidad->entregaDelPeriodo($firmante, $periodo));
    }

    /**
     * Lo que pinta una fila de solicitud (x-fila-de-solicitud), cargado de
     * una vez para no hacer una consulta por fila.
     *
     * @param  Builder<Solicitud>  $consulta
     * @return Builder<Solicitud>
     */
    private function conLoDeLaFila(Builder $consulta): Builder
    {
        return $consulta->with(['docente:id,nombre', 'docenteQueDicta:id,nombre', 'materia:id,nombre', 'casoClinico:id,nombre', 'preparacion.sala']);
    }
}
