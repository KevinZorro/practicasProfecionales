<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoSolicitud;
use App\Enums\EstadoUsuario;
use App\Enums\OrigenSolicitud;
use App\Enums\Rol;
use App\Events\SolicitudAprobada;
use App\Events\SolicitudRechazada;
use App\Exceptions\CapacidadDeEstudiantesExcedida;
use App\Exceptions\SolicitudInvalida;
use App\Exceptions\TransicionDeSolicitudInvalida;
use App\Models\CasoClinico;
use App\Models\EstudianteDeLaSesion;
use App\Models\ItemInventario;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reglas del flujo de solicitudes de escenario (RF27-RF35).
 */
final class SolicitudService
{
    /** Máximo de resultados al buscar estudiantes para una sesión. */
    public const RESULTADOS_DE_BUSQUEDA = 10;

    public function __construct(
        private readonly PreparacionService $preparaciones,
        private readonly BloqueoService $bloqueos,
    ) {}

    public function crear(User $docente, DatosNuevaSolicitud $datos): Solicitud
    {
        // Un docente bloqueado no pide escenarios nuevos (RF68, D2).
        $bloqueo = $this->bloqueos->vigenteDe($docente);

        if ($bloqueo !== null) {
            throw SolicitudInvalida::docenteBloqueado($bloqueo->motivo);
        }

        $grupo = $this->normalizarGrupo($datos->grupo);
        $estudianteIds = $this->garantizarEstudiantes($datos->estudianteIds);
        $this->garantizarCapacidad($datos->casoClinicoId, count($estudianteIds));

        return DB::transaction(function () use ($docente, $datos, $grupo, $estudianteIds): Solicitud {
            // El formato intramural viene con la solicitud del docente (RF59).
            $solicitud = Solicitud::create([
                ...$this->atributosIniciales($docente, $datos),
                'grupo' => $grupo,
                'cantidad_estudiantes' => count($estudianteIds),
                'origen' => OrigenSolicitud::Docente,
                'formato_intramural_at' => now(),
                'formato_intramural_por' => $docente->id,
            ]);
            $solicitud->items()->attach($this->itemsAAdjuntar($datos));
            $solicitud->estudiantes()->attach($estudianteIds);

            return $solicitud;
        });
    }

    /**
     * Agrega estudiantes a una sesión ya registrada: el docente que completa
     * su lista, o el laboratorio (RF28, D15). Los que ya estaban se ignoran;
     * a un retirado no se le vuelve a agregar, porque el retiro queda
     * registrado (RF69).
     *
     * @param  list<int>  $estudianteIds
     */
    public function agregarEstudiantes(Solicitud $solicitud, array $estudianteIds, User $actor): Solicitud
    {
        $this->garantizarPermiso($actor, 'gestionarParticipantes', $solicitud);
        $this->garantizarSesionAbierta($solicitud);
        $estudianteIds = $this->garantizarEstudiantes($estudianteIds);

        return DB::transaction(function () use ($solicitud, $estudianteIds): Solicitud {
            $enLista = $solicitud->estudiantes()->get();
            $retirado = $enLista->first(fn (User $e): bool => $this->fueRetirado($e) && in_array($e->id, $estudianteIds, true));

            if ($retirado instanceof User) {
                throw SolicitudInvalida::yaFueRetirado($retirado->nombre);
            }

            $nuevos = array_values(array_diff($estudianteIds, $enLista->pluck('id')->all()));
            $this->garantizarCapacidad($solicitud->caso_clinico_id, $this->presentes($solicitud) + count($nuevos));

            $solicitud->estudiantes()->attach($nuevos);
            $this->actualizarCantidad($solicitud);

            return $solicitud;
        });
    }

    /**
     * Retira a un estudiante de una sesión en curso o programada (RF69). No
     * se borra de la lista: queda con el motivo y quién lo decidió.
     */
    public function retirarEstudiante(Solicitud $solicitud, User $estudiante, string $motivo, User $actor): Solicitud
    {
        $this->garantizarPermiso($actor, 'gestionarParticipantes', $solicitud);
        $this->garantizarSesionAbierta($solicitud);
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw SolicitudInvalida::sinMotivoDeRetiro();
        }

        return DB::transaction(function () use ($solicitud, $estudiante, $motivo, $actor): Solicitud {
            $enLista = $solicitud->estudiantes()->whereKey($estudiante->id)->first();

            if (! $enLista instanceof User) {
                throw SolicitudInvalida::noEstaEnLaSesion($estudiante->nombre);
            }

            if ($this->fueRetirado($enLista)) {
                throw SolicitudInvalida::yaFueRetirado($estudiante->nombre);
            }

            $solicitud->estudiantes()->updateExistingPivot($estudiante->id, [
                'retirado_at' => now(),
                'retirado_por' => $actor->id,
                'motivo_retiro' => $motivo,
            ]);
            $this->actualizarCantidad($solicitud);

            return $solicitud;
        });
    }

    /**
     * Estudiantes que el docente puede poner en una sesión, por nombre,
     * correo o código institucional (RF28). Solo cuentas activas con el rol
     * de estudiante vigente.
     *
     * @param  list<int>  $excluir  los que ya están en la lista
     * @return Collection<int, User>
     */
    public function buscarEstudiantes(string $busqueda, array $excluir = []): Collection
    {
        if (trim($busqueda) === '') {
            return new Collection;
        }

        $aguja = '%'.mb_strtolower(trim($busqueda)).'%';

        return $this->estudiantesQuePuedenIr()
            ->whereNotIn('id', $excluir)
            ->where(static fn (Builder $o) => $o
                ->whereRaw('LOWER(nombre) LIKE ?', [$aguja])
                ->orWhereRaw('LOWER(email) LIKE ?', [$aguja])
                ->orWhereRaw('LOWER(codigo_institucional) LIKE ?', [$aguja]))
            ->orderBy('nombre')
            ->limit(self::RESULTADOS_DE_BUSQUEDA)
            ->get(['id', 'nombre', 'email', 'codigo_institucional']);
    }

    /**
     * Estudiantes por código institucional, para pegar de una vez la lista
     * del grupo. Devuelve los encontrados y los códigos que no corresponden a
     * ningún estudiante activo.
     *
     * @param  list<string>  $codigos
     * @return array{encontrados: Collection<int, User>, desconocidos: list<string>}
     */
    public function estudiantesPorCodigo(array $codigos): array
    {
        $codigos = array_values(array_unique(array_filter(array_map('trim', $codigos), static fn (string $c): bool => $c !== '')));

        $encontrados = $this->estudiantesQuePuedenIr()
            ->whereIn('codigo_institucional', $codigos)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'email', 'codigo_institucional']);

        return [
            'encontrados' => $encontrados,
            'desconocidos' => array_values(array_diff($codigos, $encontrados->pluck('codigo_institucional')->all())),
        ];
    }

    /**
     * RF74: ningún escenario admite más estudiantes de los que el ADMIN le
     * registró.
     *
     * Un caso sin capacidad definida no bloquea: el dato es del ADMIN y
     * todavía puede faltarle. Impedir la práctica por un campo que nadie ha
     * llenado sería peor que no limitarla, y en pantalla se lee como
     * "sin definir".
     */
    public function garantizarCapacidad(int $casoClinicoId, int $cantidad): void
    {
        $caso = CasoClinico::findOrFail($casoClinicoId);
        $maximo = $caso->capacidad_maxima_estudiantes;

        if ($maximo === null || $cantidad <= $maximo) {
            return;
        }

        throw CapacidadDeEstudiantesExcedida::para($caso, $cantidad);
    }

    /**
     * Una o dos letras, en mayúscula: "a" y "A" son el mismo grupo.
     */
    public function normalizarGrupo(string $grupo): string
    {
        $grupo = mb_strtoupper(trim($grupo));

        if (preg_match('/^[A-Z]{1,2}$/', $grupo) !== 1) {
            throw SolicitudInvalida::grupoInvalido($grupo);
        }

        return $grupo;
    }

    /**
     * Al menos un estudiante, y todos con cuenta activa y el rol de
     * estudiante vigente. Los repetidos cuentan una vez.
     *
     * @param  list<int>  $estudianteIds
     * @return list<int>
     */
    private function garantizarEstudiantes(array $estudianteIds): array
    {
        $estudianteIds = array_values(array_unique($estudianteIds));

        if ($estudianteIds === []) {
            throw SolicitudInvalida::sinEstudiantes();
        }

        $validos = $this->estudiantesQuePuedenIr()->whereIn('id', $estudianteIds)->pluck('id')->all();
        $invalidos = array_diff($estudianteIds, $validos);

        if ($invalidos !== []) {
            throw SolicitudInvalida::noSonEstudiantesActivos(
                User::query()->whereIn('id', $invalidos)->orderBy('nombre')->pluck('nombre')->all(),
            );
        }

        return $estudianteIds;
    }

    /**
     * La lista se cambia mientras la sesión no haya pasado ni se haya
     * rechazado. "Hoy" cuenta: se retira a alguien de la sesión en curso.
     */
    private function garantizarSesionAbierta(Solicitud $solicitud): void
    {
        $cerrada = $solicitud->estado === EstadoSolicitud::Rechazada
            || $solicitud->fecha->toDateString() < now()->toDateString();

        if ($cerrada) {
            throw SolicitudInvalida::sesionCerrada();
        }
    }

    /** El dato del retiro viaja en el pivote de la lista ("participacion"). */
    private function fueRetirado(User $estudiante): bool
    {
        $participacion = $estudiante->getRelation('participacion');

        return $participacion instanceof EstudianteDeLaSesion && $participacion->fueRetirado();
    }

    private function presentes(Solicitud $solicitud): int
    {
        return $solicitud->estudiantesPresentes()->count();
    }

    /**
     * "cantidad_estudiantes" sigue a la lista: es la que leen los reportes
     * (RF54). Una sesión apartada sin lista todavía conserva la cantidad que
     * se registró con ella (RF57).
     */
    private function actualizarCantidad(Solicitud $solicitud): void
    {
        if (! $solicitud->estudiantes()->exists()) {
            return;
        }

        $solicitud->update(['cantidad_estudiantes' => $this->presentes($solicitud)]);
    }

    /**
     * @throws AuthorizationException
     */
    private function garantizarPermiso(User $actor, string $accion, Solicitud $solicitud): void
    {
        if ($actor->cannot($accion, $solicitud)) {
            throw new AuthorizationException(sprintf('El usuario no tiene permiso para "%s" esta solicitud.', $accion));
        }
    }

    /**
     * El rol pasa por el filtro de vigencia de User::roles() (regla 13), así
     * que un rol de estudiante vencido tampoco cuenta.
     *
     * @return Builder<User>
     */
    private function estudiantesQuePuedenIr(): Builder
    {
        return User::query()
            ->role(Rol::Estudiante->value)
            ->where('estado', EstadoUsuario::Activo);
    }

    /**
     * Inventario que el caso clínico necesita, para precargar el formulario
     * (RF29). Es un punto de partida: el docente puede ajustar cantidades y
     * agregar equipos antes de enviar.
     *
     * @return Collection<int, ItemInventario>
     */
    public function itemsSugeridos(CasoClinico $casoClinico): Collection
    {
        return $casoClinico->items()->get();
    }

    /**
     * Historial de un docente (RF35), listo para pintar: trae materia, caso
     * clínico y la sala si el administrativo ya la asignó.
     *
     * @return LengthAwarePaginator<int, Solicitud>
     */
    public function historialDelDocente(User $docente, ?EstadoSolicitud $estado = null, int $porPagina = 15): LengthAwarePaginator
    {
        return Solicitud::query()
            ->delDocente($docente)
            ->when($estado instanceof EstadoSolicitud, fn (Builder $c) => $c->enEstado($estado))
            ->with(['materia', 'casoClinico', 'preparacion.sala'])
            ->orderByDesc('fecha')
            ->orderByDesc('hora_inicio')
            ->paginate($porPagina);
    }

    /**
     * Bandeja de revisión (RF31-RF33), ordenada por la fecha de la práctica:
     * lo que ocurre antes se atiende antes.
     *
     * @return LengthAwarePaginator<int, Solicitud>
     */
    public function bandeja(?EstadoSolicitud $estado = null, int $porPagina = 15): LengthAwarePaginator
    {
        return Solicitud::query()
            ->when($estado instanceof EstadoSolicitud, fn (Builder $c) => $c->enEstado($estado))
            ->with(['docente', 'materia', 'casoClinico', 'preparacion.sala'])
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->paginate($porPagina);
    }

    /**
     * Una solicitud con todo lo que el detalle de la bandeja enseña.
     */
    public function paraDetalle(Solicitud $solicitud): Solicitud
    {
        return $solicitud->load([
            'docente', 'materia', 'casoClinico', 'preparacion.sala', 'items',
            'estudiantes' => static fn ($consulta) => $consulta->orderBy('nombre'),
        ]);
    }

    public function marcarRevisada(Solicitud $solicitud, User $administrativo): Solicitud
    {
        $this->garantizarTransicion($solicitud, EstadoSolicitud::Revisada);

        $solicitud->update([
            'estado' => EstadoSolicitud::Revisada,
            'revisada_por' => $administrativo->id,
            'revisada_at' => now(),
        ]);

        return $solicitud;
    }

    public function aprobar(Solicitud $solicitud, User $coordinador): Solicitud
    {
        $this->garantizarTransicion($solicitud, EstadoSolicitud::Aprobada);

        DB::transaction(function () use ($solicitud, $coordinador): void {
            $solicitud->update($this->atributosDeResolucion(EstadoSolicitud::Aprobada, $coordinador));
            $this->preparaciones->crearDesdeSolicitud($solicitud);
        });

        // Fuera de la transacción: si algo la revierte, no debe salir correo.
        SolicitudAprobada::dispatch($solicitud);

        return $solicitud;
    }

    /**
     * Rechaza en cualquiera de las dos fases (RF30, RF31). Si la rechaza el
     * administrativo, "revisada_por" queda nulo: así se distingue la que no
     * pasó la revisión de la que coordinación rechazó después.
     */
    public function rechazar(Solicitud $solicitud, User $actor, ?string $motivo = null): Solicitud
    {
        $this->garantizarTransicion($solicitud, EstadoSolicitud::Rechazada);

        $solicitud->update([
            ...$this->atributosDeResolucion(EstadoSolicitud::Rechazada, $actor),
            'motivo_rechazo' => $motivo,
        ]);

        SolicitudRechazada::dispatch($solicitud);

        return $solicitud;
    }

    /**
     * Reservas aprobadas de un rango de fechas, con la sala si el
     * administrativo ya la asignó (RF34).
     *
     * @return Collection<int, Solicitud>
     */
    public function paraCalendario(string $desde, string $hasta): Collection
    {
        return Solicitud::query()
            ->aprobadasEntre($desde, $hasta)
            ->with(['docente', 'materia', 'casoClinico', 'preparacion.sala'])
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function atributosIniciales(User $docente, DatosNuevaSolicitud $datos): array
    {
        return [
            'docente_id' => $docente->id,
            'materia_id' => $datos->materiaId,
            'caso_clinico_id' => $datos->casoClinicoId,
            'tipo' => $datos->tipo,
            'fecha' => $datos->fecha,
            'hora_inicio' => $datos->horaInicio,
            'hora_fin' => $datos->horaFin,
            'estado' => EstadoSolicitud::Pendiente,
            'observaciones' => $datos->observaciones,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function atributosDeResolucion(EstadoSolicitud $estado, User $actor): array
    {
        return [
            'estado' => $estado,
            'resuelta_por' => $actor->id,
            'resuelta_at' => now(),
        ];
    }

    /**
     * @return array<int, array{cantidad: int}>
     */
    private function itemsAAdjuntar(DatosNuevaSolicitud $datos): array
    {
        $cantidades = $datos->items !== []
            ? $datos->items
            : $this->cantidadesDelCasoClinico($datos->casoClinicoId);

        return array_map(
            static fn (int $cantidad): array => ['cantidad' => $cantidad],
            $cantidades,
        );
    }

    /**
     * @return array<int, int>
     */
    private function cantidadesDelCasoClinico(int $casoClinicoId): array
    {
        return $this->itemsSugeridos(CasoClinico::findOrFail($casoClinicoId))
            ->mapWithKeys(static fn (ItemInventario $item): array => [
                $item->id => (int) $item->pivot->cantidad,
            ])
            ->all();
    }

    private function garantizarTransicion(Solicitud $solicitud, EstadoSolicitud $destino): void
    {
        $permitidos = $this->transicionesPermitidas()[$solicitud->estado->value];

        if (! in_array($destino, $permitidos, true)) {
            throw TransicionDeSolicitudInvalida::de($solicitud->estado, $destino);
        }
    }

    /**
     * El docente solicita, el administrativo acepta (revisa) o rechaza, y
     * el coordinador aprueba o rechaza lo revisado. Sin atajos: una
     * solicitud pendiente no se aprueba sin pasar por revisión, y una ya
     * resuelta no se reabre.
     *
     * @return array<string, list<EstadoSolicitud>>
     */
    private function transicionesPermitidas(): array
    {
        return [
            EstadoSolicitud::Pendiente->value => [EstadoSolicitud::Revisada, EstadoSolicitud::Rechazada],
            EstadoSolicitud::Revisada->value => [EstadoSolicitud::Aprobada, EstadoSolicitud::Rechazada],
            EstadoSolicitud::Aprobada->value => [],
            EstadoSolicitud::Rechazada->value => [],
        ];
    }
}
