<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccionAuditada;
use App\Enums\EstadoUsuario;
use App\Enums\Rol;
use App\Exceptions\SupervisionInvalida;
use App\Models\Evaluacion;
use App\Models\EvaluacionEstudiante;
use App\Models\PeriodoAcademico;
use App\Models\Supervision;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Docentes supervisados y lo que sus supervisores consultan (RF77).
 *
 * El ADMIN eleva a un docente a docente supervisor (AsignacionDeRolService)
 * y aquí decide qué docentes tiene a su cargo, unos siete, sin tope; un
 * docente puede tener varios supervisores. El supervisor solo consulta los
 * resultados de las evaluaciones que esos docentes hicieron a sus
 * estudiantes: no solicita, no evalúa y no gestiona nada más.
 *
 * La lectura es una sola consulta, la del reporte de resultados de
 * evaluación (RF55) limitada a los docentes supervisados: la pantalla, el
 * PDF y el Excel salen de la misma, igual que en los reportes. Y «los
 * docentes supervisados» sale siempre de la base, nunca de lo que mande la
 * petición: pedir el docente de otro supervisor no devuelve nada.
 *
 * Una evaluación es de quien la hizo (`evaluaciones.docente_id`), que en una
 * sesión con sustitución es quien dictó (RF73, D23).
 */
final class SupervisionService
{
    public const POR_PAGINA = 15;

    /** Los docentes que se ofrecen al buscar a quién asignar. */
    public const CANDIDATOS = 20;

    /** Valor de `periodo` para consultar todo el historial. */
    public const TODOS_LOS_PERIODOS = 'todos';

    public function __construct(
        private readonly ReporteService $reportes,
        private readonly PeriodoAcademicoService $periodos,
        private readonly BitacoraService $bitacora,
    ) {}

    public function asignarDocente(User $actor, User $supervisor, User $docente): Supervision
    {
        $this->garantizarGestion($actor);

        if ($supervisor->is($docente)) {
            throw SupervisionInvalida::unoMismo();
        }

        if (! $supervisor->hasRole(Rol::Supervisor->value)) {
            throw SupervisionInvalida::noEsSupervisor();
        }

        if (! $docente->hasRole(Rol::Docente->value)) {
            throw SupervisionInvalida::noEsDocente();
        }

        return DB::transaction(function () use ($actor, $supervisor, $docente): Supervision {
            if ($this->consulta($supervisor, $docente)->exists()) {
                throw SupervisionInvalida::yaLoSupervisa();
            }

            $supervision = new Supervision;
            $supervision->supervisor_id = $supervisor->id;
            $supervision->docente_id = $docente->id;
            $supervision->asignado_por = $actor->id;
            $supervision->save();

            $this->bitacora->registrar(
                AccionAuditada::DocenteSupervisadoAsignado,
                $actor,
                $supervision,
                sprintf('Puso a %s a cargo de %s como docente supervisado.', $docente->nombre, $supervisor->nombre),
            );

            return $supervision;
        });
    }

    public function retirarDocente(User $actor, User $supervisor, User $docente): void
    {
        $this->garantizarGestion($actor);

        DB::transaction(function () use ($actor, $supervisor, $docente): void {
            $supervision = $this->consulta($supervisor, $docente)->lockForUpdate()->first();

            if ($supervision === null) {
                throw SupervisionInvalida::noLoSupervisa();
            }

            // La bitácora apunta a la fila que se borra: su descripción es lo
            // que queda legible, no la relación.
            $this->bitacora->registrar(
                AccionAuditada::DocenteSupervisadoRetirado,
                $actor,
                $supervision,
                sprintf('Retiró a %s de los docentes supervisados por %s.', $docente->nombre, $supervisor->nombre),
            );

            $supervision->delete();
        });
    }

    /**
     * Los docentes a cargo de un supervisor, por nombre.
     *
     * @return Collection<int, User>
     */
    public function docentesDe(User $supervisor): Collection
    {
        return $supervisor->docentesSupervisados()
            ->select(['users.id', 'users.nombre', 'users.email'])
            ->orderBy('users.nombre')
            ->get();
    }

    /**
     * Docentes que todavía no están a cargo de este supervisor, para
     * ofrecerlos al asignar. Solo cuentas con vigencia institucional y no
     * deshabilitadas: asignar a quien no puede entrar no sirve de nada.
     *
     * @return Collection<int, User>
     */
    public function candidatosPara(User $supervisor, ?string $busqueda = null): Collection
    {
        $aguja = '%'.mb_strtolower(trim((string) $busqueda)).'%';

        return User::query()
            ->role(Rol::Docente->value)
            ->whereKeyNot($supervisor->id)
            ->where('estado', EstadoUsuario::Activo)
            ->whereNull('deshabilitado_at')
            ->whereDoesntHave('supervisores', fn (Builder $c) => $c->whereKey($supervisor->id))
            ->when(trim((string) $busqueda) !== '', fn (Builder $c) => $c->where(
                fn (Builder $o) => $o->whereRaw('LOWER(nombre) LIKE ?', [$aguja])->orWhereRaw('LOWER(email) LIKE ?', [$aguja]),
            ))
            ->select(['id', 'nombre', 'email'])
            ->orderBy('nombre')
            ->limit(self::CANDIDATOS)
            ->get();
    }

    /**
     * Resultados de las evaluaciones finalizadas que hicieron los docentes
     * de este supervisor, un renglón por estudiante evaluado, con su
     * checklist (RF77). Con periodo, solo las sesiones de ese periodo;
     * con docente, solo las de ese docente, si está a su cargo.
     *
     * @return Builder<EvaluacionEstudiante>
     */
    public function resultados(User $supervisor, ?PeriodoAcademico $periodo = null, ?int $docenteId = null): Builder
    {
        return $this->reportes->resultadosDeEvaluacion($this->filtroDe($supervisor, $periodo, $docenteId))
            ->with(['evaluacion.items', 'items']);
    }

    /**
     * El filtro que usan la pantalla, el PDF y el Excel del supervisor.
     */
    public function filtroDe(User $supervisor, ?PeriodoAcademico $periodo = null, ?int $docenteId = null): FiltroReporte
    {
        $this->garantizarQueSupervisa($supervisor);

        $garantizados = $this->idsDeLosDocentesDe($supervisor);
        $ids = $docenteId === null
            ? $garantizados
            : array_values(array_intersect($garantizados, [$docenteId]));

        return new FiltroReporte(
            desde: $periodo?->abierto_at?->toDateString(),
            hasta: $periodo?->cerrado_at?->toDateString(),
            docenteIds: $ids,
        );
    }

    /**
     * El periodo que pide la pantalla o la descarga: sin valor, el vigente
     * (RF75); `todos`, ninguno (todo el historial); un número, ese periodo.
     */
    public function periodoDe(?string $valor): ?PeriodoAcademico
    {
        if ($valor === null || $valor === '') {
            return $this->periodos->vigente();
        }

        if ($valor === self::TODOS_LOS_PERIODOS) {
            return null;
        }

        return PeriodoAcademico::query()->findOrFail((int) $valor);
    }

    /**
     * Los periodos que se pueden consultar, del más reciente al más antiguo.
     *
     * @return Collection<int, PeriodoAcademico>
     */
    public function periodosConsultables(): Collection
    {
        return PeriodoAcademico::query()->orderByDesc('abierto_at')->orderByDesc('id')->get();
    }

    /** Cuántas evaluaciones finalizadas hizo el equipo de este supervisor en el periodo. */
    public function cuantasEvaluaciones(User $supervisor, ?PeriodoAcademico $periodo): int
    {
        $this->garantizarQueSupervisa($supervisor);

        return Evaluacion::query()
            ->finalizadas()
            ->whereIn('docente_id', $this->idsDeLosDocentesDe($supervisor))
            ->whereHas('solicitud', fn (Builder $s) => $s
                ->when($periodo?->abierto_at !== null, fn (Builder $c) => $c->whereDate('fecha', '>=', $periodo?->abierto_at?->toDateString()))
                ->when($periodo?->cerrado_at !== null, fn (Builder $c) => $c->whereDate('fecha', '<=', $periodo?->cerrado_at?->toDateString())))
            ->count();
    }

    /** @return list<int> */
    public function idsDeLosDocentesDe(User $supervisor): array
    {
        return Supervision::query()
            ->where('supervisor_id', $supervisor->id)
            ->pluck('docente_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /** @return Builder<Supervision> */
    private function consulta(User $supervisor, User $docente): Builder
    {
        return Supervision::query()
            ->where('supervisor_id', $supervisor->id)
            ->where('docente_id', $docente->id);
    }

    /**
     * La Policy decide; el Service la consulta para que la regla valga
     * también fuera de una petición HTTP.
     *
     * @throws AuthorizationException
     */
    private function garantizarQueSupervisa(User $supervisor): void
    {
        // Las filas de docente_supervisor sobreviven a la revocación del rol
        // (D22): sin esta comprobación, quien deja de ser supervisor
        // seguiría leyendo lo de su equipo.
        if ($supervisor->cannot('supervisar', Evaluacion::class)) {
            throw new AuthorizationException('Solo un docente supervisor consulta las evaluaciones de sus docentes.');
        }
    }

    /**
     * @throws AuthorizationException
     */
    private function garantizarGestion(User $actor): void
    {
        if ($actor->cannot('gestionar', Supervision::class)) {
            throw new AuthorizationException('Solo el administrador de la plataforma reparte docentes entre supervisores.');
        }
    }
}
