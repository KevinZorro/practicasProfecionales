<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Rol;
use App\Exceptions\AsignacionDeRolInvalida;
use App\Models\AsignacionDeRol;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Asignación de roles, con y sin vigencia (RF63, RF64).
 *
 * Dos necesidades que son la misma pieza: el pasante hace el trabajo del
 * administrativo pero se va antes de que terminen las clases, y la
 * coordinadora delega su facultad de aprobar mientras está en consejo. Las
 * dos son un rol con fecha de fin que deja de valer solo.
 *
 * El vencimiento NO se hace cumplir aquí: lo hace el filtro de
 * User::roles(), en SQL, en cada consulta de permisos. Este Service escribe;
 * quien decide si un rol da permisos hoy es la relación. Por eso no hay
 * ningún job que "caducar roles", y no hay que escribirlo: si alguien lo
 * añade creyendo que hace falta, el vencimiento pasaría a depender de que
 * corra, que es justo lo que se evitó.
 *
 * Tampoco se llama nunca a assignRole() de spatie: calcula lo que el usuario
 * ya tiene leyendo la relación filtrada, así que con una fila vencida en el
 * pivote intentaría insertar una que ya existe y reventaría contra la llave
 * primaria. El pivote se escribe aquí, explícito.
 */
final class AsignacionDeRolService
{
    /** Con cuánta antelación avisar de un vencimiento (RF63, punto 4). */
    public const DIAS_DE_AVISO = 14;

    public const POR_PAGINA = 15;

    /**
     * Asigna un rol. Sin "hasta" es permanente; con "hasta" vence al final
     * de ese día.
     */
    public function asignar(
        User $actor,
        User $usuario,
        Rol $rol,
        ?string $hasta = null,
        ?string $motivo = null,
    ): AsignacionDeRol {
        $this->garantizarPermiso($actor, 'asignar');
        $this->garantizarMotivo($rol, $motivo);
        $this->garantizarFechaDeFin($hasta);
        $this->garantizarQueNoLoTiene($usuario, $rol);

        $desde = CarbonImmutable::now()->toDateString();
        $role = $this->role($rol);

        return DB::transaction(function () use ($usuario, $role, $desde, $hasta, $motivo, $actor): AsignacionDeRol {
            $this->escribirPivote($usuario, $role, $desde, $hasta);

            $asignacion = AsignacionDeRol::create([
                'user_id' => $usuario->id,
                'role_id' => $role->id,
                'desde' => $desde,
                'hasta' => $hasta,
                'motivo' => $motivo,
                'asignado_por' => $actor->id,
            ]);

            $this->olvidarRolesCargados($usuario);

            return $asignacion;
        });
    }

    /**
     * Revoca un rol con efecto inmediato (RF63, punto 3).
     *
     * Borra la fila del pivote en vez de acortar "hasta". Con vigencia por
     * día, poner hasta = hoy dejaría el rol vivo hasta medianoche, que es
     * justo lo que la revocación quiere evitar; y hasta = ayer dejaría una
     * fila con el rango al revés. Sin fila no hay permiso, y el corte es en
     * la misma petición.
     *
     * El rastro no se pierde: queda en asignaciones_de_rol con quién revocó
     * y cuándo.
     */
    public function revocar(User $actor, User $usuario, Rol $rol, ?string $motivo = null): AsignacionDeRol
    {
        $this->garantizarPermiso($actor, 'revocar');

        $fila = $this->filaDelPivote($usuario, $this->role($rol));

        if ($fila === null) {
            throw AsignacionDeRolInvalida::noTieneEseRol($rol);
        }

        $role = $this->role($rol);

        return DB::transaction(function () use ($usuario, $role, $fila, $actor, $motivo): AsignacionDeRol {
            DB::table($this->pivote())
                ->where('role_id', $role->id)
                ->where('model_id', $usuario->id)
                ->where('model_type', $usuario->getMorphClass())
                ->delete();

            $asignacion = $this->asignacionAbierta($usuario, $role)
                ?? $this->asignacionRetroactiva($usuario, $role, $fila);

            $asignacion->update([
                'revocada_at' => now(),
                'revocada_por' => $actor->id,
                'motivo' => $this->motivoDeLaRevocacion($asignacion, $motivo),
            ]);

            $this->olvidarRolesCargados($usuario);

            return $asignacion;
        });
    }

    /**
     * Historial completo de un usuario: vigentes, vencidas y revocadas.
     *
     * @return Collection<int, AsignacionDeRol>
     */
    public function historialDe(User $usuario): Collection
    {
        return AsignacionDeRol::query()
            ->where('user_id', $usuario->id)
            ->with(['role:id,name', 'asignadaPor:id,nombre', 'revocadaPor:id,nombre'])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Roles temporales que vencen dentro de los próximos días (RF63,
     * punto 4). Sale del pivote, que es lo que da permisos: lo revocado ya
     * no tiene fila y lo vencido lo descarta el filtro de User::roles().
     *
     * @return Collection<int, User>
     */
    public function proximosAVencer(?int $dias = null): Collection
    {
        $limite = CarbonImmutable::now()->addDays($dias ?? self::DIAS_DE_AVISO)->toDateString();

        return User::query()
            ->whereHas('roles', fn (Builder $c) => $this->queVencenAntesDe($c, $limite))
            ->with(['roles' => fn (MorphToMany $r) => $this->queVencenAntesDe($r, $limite)])
            ->orderBy('nombre')
            ->get();
    }

    /**
     * El mismo criterio para el whereHas y para el with: si divergen, la
     * pantalla enseña personas sin ningún rol próximo a vencer.
     *
     * @param  Builder<User>|MorphToMany<Role, User>  $consulta
     */
    private function queVencenAntesDe(Builder|MorphToMany $consulta, string $limite): void
    {
        $consulta->whereNotNull($this->pivote().'.hasta')
            ->where($this->pivote().'.hasta', '<=', $limite);
    }

    /**
     * Hasta cuándo vale este rol para esta persona. Null si es permanente o
     * si no lo tiene: quien pregunta ya sabe que lo tiene.
     */
    public function vigenciaDe(User $usuario, Rol $rol): ?CarbonImmutable
    {
        $fila = $this->filaDelPivote($usuario, $this->role($rol));

        return $fila?->hasta === null ? null : CarbonImmutable::parse($fila->hasta);
    }

    /**
     * Listado para la pantalla del ADMIN: personas con los roles que tienen
     * vigentes hoy.
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function usuariosConSusRoles(?string $busqueda = null, int $porPagina = self::POR_PAGINA): LengthAwarePaginator
    {
        return User::query()
            ->with('roles')
            ->when(
                is_string($busqueda) && trim($busqueda) !== '',
                function (Builder $consulta) use ($busqueda): void {
                    $aguja = '%'.mb_strtolower(trim((string) $busqueda)).'%';
                    $consulta->where(static function (Builder $o) use ($aguja): void {
                        $o->whereRaw('LOWER(nombre) LIKE ?', [$aguja])
                            ->orWhereRaw('LOWER(email) LIKE ?', [$aguja])
                            ->orWhereRaw('LOWER(codigo_institucional) LIKE ?', [$aguja]);
                    });
                },
            )
            ->orderBy('nombre')
            ->paginate($porPagina);
    }

    /**
     * El pivote se escribe a mano, no con attach(): puede existir ya una
     * fila vencida —la del pasante del semestre pasado— y la llave primaria
     * del pivote no admite una segunda.
     */
    private function escribirPivote(User $usuario, Role $role, string $desde, ?string $hasta): void
    {
        DB::table($this->pivote())->updateOrInsert(
            [
                'role_id' => $role->id,
                'model_id' => $usuario->id,
                'model_type' => $usuario->getMorphClass(),
            ],
            ['desde' => $desde, 'hasta' => $hasta],
        );
    }

    private function filaDelPivote(User $usuario, Role $role): ?object
    {
        return DB::table($this->pivote())
            ->where('role_id', $role->id)
            ->where('model_id', $usuario->id)
            ->where('model_type', $usuario->getMorphClass())
            ->first();
    }

    /** La asignación viva de este rol, si el historial la tiene. */
    private function asignacionAbierta(User $usuario, Role $role): ?AsignacionDeRol
    {
        return AsignacionDeRol::query()
            ->where('user_id', $usuario->id)
            ->where('role_id', $role->id)
            ->whereNull('revocada_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Rol repartido antes de que existiera el historial —los del seeder—.
     * Se deja constancia de lo que se sabe: desde cuándo valía según el
     * pivote, y sin responsable, porque no consta.
     */
    private function asignacionRetroactiva(User $usuario, Role $role, object $fila): AsignacionDeRol
    {
        return AsignacionDeRol::create([
            'user_id' => $usuario->id,
            'role_id' => $role->id,
            'desde' => $fila->desde,
            'hasta' => $fila->hasta,
            'asignado_por' => null,
        ]);
    }

    private function motivoDeLaRevocacion(AsignacionDeRol $asignacion, ?string $motivo): ?string
    {
        if ($motivo === null || trim($motivo) === '') {
            return $asignacion->motivo;
        }

        return trim($motivo);
    }

    /**
     * Deja que la siguiente comprobación de permisos vuelva a consultar.
     *
     * Sin esto, un usuario cargado antes de la revocación seguiría diciendo
     * que tiene el rol durante el resto de la petición, que es exactamente
     * el agujero que la revocación inmediata tiene que cerrar.
     */
    private function olvidarRolesCargados(User $usuario): void
    {
        $usuario->unsetRelation('roles');
    }

    private function garantizarQueNoLoTiene(User $usuario, Rol $rol): void
    {
        if ($usuario->hasRole($rol->value)) {
            throw AsignacionDeRolInvalida::elRolYaEstaVigente($rol);
        }
    }

    /**
     * RF63: delegar la facultad de aprobar exige justificación escrita. Que
     * un pasante entre a inventario, no.
     */
    private function garantizarMotivo(Rol $rol, ?string $motivo): void
    {
        if ($rol === Rol::Coordinador && ($motivo === null || trim($motivo) === '')) {
            throw AsignacionDeRolInvalida::laElevacionExigeMotivo();
        }
    }

    private function garantizarFechaDeFin(?string $hasta): void
    {
        if ($hasta !== null && $hasta < CarbonImmutable::now()->toDateString()) {
            throw AsignacionDeRolInvalida::laFechaDeFinYaPaso($hasta);
        }
    }

    /**
     * La Policy decide; el Service la consulta para que la regla valga
     * también fuera de una petición HTTP.
     *
     * @throws AuthorizationException
     */
    private function garantizarPermiso(User $actor, string $accion): void
    {
        if ($actor->cannot($accion, AsignacionDeRol::class)) {
            throw new AuthorizationException(
                sprintf('El usuario no tiene permiso para "%s" roles.', $accion),
            );
        }
    }

    private function role(Rol $rol): Role
    {
        return Role::findByName($rol->value);
    }

    private function pivote(): string
    {
        return config('permission.table_names.model_has_roles');
    }
}
