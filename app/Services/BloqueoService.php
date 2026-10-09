<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccionAuditada;
use App\Enums\Rol;
use App\Exceptions\BloqueoInvalido;
use App\Models\Bloqueo;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bloqueo de estudiantes y docentes para el uso del laboratorio (RF68).
 *
 * El bloqueo no tiene fecha de fin: dura hasta que alguien lo levante, con
 * motivo (D2 de docs/trazabilidad.md). Qué significa estar bloqueado lo
 * responde ParticipacionService, junto con el formato de confidencialidad.
 */
final class BloqueoService
{
    public const POR_PAGINA = 15;

    public const RESULTADOS_DE_BUSQUEDA = 10;

    public function __construct(private readonly BitacoraService $bitacora) {}

    public function bloquear(User $persona, string $motivo, User $actor): Bloqueo
    {
        $this->garantizarPermiso($actor, 'create', Bloqueo::class);
        $motivo = $this->garantizarMotivo($motivo);

        if (! $persona->hasAnyRole(Rol::queFirmanElFormato())) {
            throw BloqueoInvalido::noEsParticipante($persona);
        }

        return DB::transaction(function () use ($persona, $motivo, $actor): Bloqueo {
            if ($this->vigenteDe($persona) instanceof Bloqueo) {
                throw BloqueoInvalido::yaEstaBloqueado($persona);
            }

            $bloqueo = Bloqueo::create([
                'user_id' => $persona->id,
                'motivo' => $motivo,
                'bloqueado_por' => $actor->id,
            ]);
            $this->bitacora->registrar(AccionAuditada::BloqueoRegistrado, $actor, $persona, sprintf('Bloqueó a %s para el uso del laboratorio.', $persona->nombre), $motivo);

            return $bloqueo;
        });
    }

    public function levantar(Bloqueo $bloqueo, string $motivo, User $actor): Bloqueo
    {
        $this->garantizarPermiso($actor, 'levantar', $bloqueo);
        $motivo = $this->garantizarMotivo($motivo);

        if (! $bloqueo->estaVigente()) {
            throw BloqueoInvalido::yaFueLevantado();
        }

        return DB::transaction(function () use ($bloqueo, $motivo, $actor): Bloqueo {
            $bloqueo->levantado_at = now();
            $bloqueo->levantado_por = $actor->id;
            $bloqueo->motivo_levantamiento = $motivo;
            $bloqueo->save();

            $persona = $bloqueo->persona;
            $this->bitacora->registrar(AccionAuditada::BloqueoLevantado, $actor, $persona, sprintf('Levantó el bloqueo de %s.', $persona->nombre), $motivo);

            return $bloqueo;
        });
    }

    public function vigenteDe(User $persona): ?Bloqueo
    {
        return Bloqueo::query()->vigentes()->where('user_id', $persona->id)->first();
    }

    /**
     * Bloqueos vigentes de varias personas, por id de persona. Una sola
     * consulta, para las listas de una sesión.
     *
     * @param  list<int>  $personaIds
     * @return Collection<int, Bloqueo>
     */
    public function vigentesDe(array $personaIds): Collection
    {
        return Bloqueo::query()
            ->vigentes()
            ->whereIn('user_id', $personaIds)
            ->get()
            ->keyBy('user_id');
    }

    /**
     * Vigentes primero, después el historial.
     *
     * @return LengthAwarePaginator<int, Bloqueo>
     */
    public function listado(?string $busqueda = null, bool $soloVigentes = true, int $porPagina = self::POR_PAGINA): LengthAwarePaginator
    {
        return Bloqueo::query()
            ->when($soloVigentes, static fn (Builder $c) => $c->vigentes())
            ->when(
                is_string($busqueda) && trim($busqueda) !== '',
                fn (Builder $c) => $c->whereHas('persona', fn (Builder $p) => $this->buscarPersona($p, (string) $busqueda)),
            )
            ->with(['persona:id,nombre,email,codigo_institucional', 'bloqueadoPor:id,nombre', 'levantadoPor:id,nombre'])
            ->orderByRaw('levantado_at IS NOT NULL')
            ->latest()
            ->latest('id')
            ->paginate($porPagina);
    }

    /**
     * Estudiantes y docentes sin bloqueo vigente que coinciden con la
     * búsqueda: a quién se puede bloquear.
     *
     * @return Collection<int, User>
     */
    public function bloqueables(string $busqueda): Collection
    {
        if (trim($busqueda) === '') {
            return new Collection;
        }

        return User::query()
            ->role(Rol::queFirmanElFormato())
            ->whereDoesntHave('bloqueos', static fn (Builder $b) => $b->vigentes())
            ->where(fn (Builder $p) => $this->buscarPersona($p, $busqueda))
            ->orderBy('nombre')
            ->limit(self::RESULTADOS_DE_BUSQUEDA)
            ->get(['id', 'nombre', 'email', 'codigo_institucional']);
    }

    /** @param  Builder<User>  $consulta */
    private function buscarPersona(Builder $consulta, string $busqueda): void
    {
        $aguja = '%'.mb_strtolower(trim($busqueda)).'%';

        $consulta->where(static fn (Builder $o) => $o
            ->whereRaw('LOWER(nombre) LIKE ?', [$aguja])
            ->orWhereRaw('LOWER(email) LIKE ?', [$aguja])
            ->orWhereRaw('LOWER(codigo_institucional) LIKE ?', [$aguja]));
    }

    private function garantizarMotivo(string $motivo): string
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw BloqueoInvalido::sinMotivo();
        }

        return $motivo;
    }

    /**
     * @param  Bloqueo|class-string  $sobre
     *
     * @throws AuthorizationException
     */
    private function garantizarPermiso(User $actor, string $accion, Bloqueo|string $sobre): void
    {
        if ($actor->cannot($accion, $sobre)) {
            throw new AuthorizationException(sprintf('El usuario no tiene permiso para "%s" un bloqueo.', $accion));
        }
    }
}
