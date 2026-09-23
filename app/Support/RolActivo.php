<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Rol;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Session\Session;
use Spatie\Permission\Models\Role;

/**
 * Rol con el que el usuario está mirando el sistema ahora mismo (RF21).
 *
 * Un usuario puede tener varios roles a la vez —la coordinadora es además
 * docente—, pero la interfaz enseña uno solo cada vez. El rol elegido vive
 * en sesión, nunca en base de datos: es una preferencia de la sesión, no un
 * dato del usuario.
 *
 * La pieza que hace que esto funcione es aplicar(): restringe la relación
 * de roles ya cargada al rol activo. A partir de ahí, cada hasRole() que
 * consultan las Policies responde sobre ese único rol, así que cambiar de
 * rol cambia los permisos efectivos sin tocar ni una Policy. Esta capa no
 * vuelve a decidir nada; solo estrecha lo que el sistema de permisos ve.
 *
 * Los roles vencidos no aparecen por ningún lado, y no hace falta hacer
 * nada para eso: todo lo que se lee aquí sale de User::roles(), que ya
 * filtra por vigencia en SQL (RF63, RF64).
 */
final class RolActivo
{
    public const CLAVE_DE_SESION = 'rol_activo';

    /**
     * Roles asignados, por usuario, durante esta petición, con su fecha de
     * fin (null = permanente).
     *
     * Hace falta porque aplicar() deja la relación "roles" recortada al rol
     * activo: a partir de ese momento, leerla ya no dice qué roles tiene el
     * usuario, sino cuál está usando. Los métodos que necesitan la verdad
     * consultan la base, y esto evita repetir la consulta.
     *
     * @var array<int, array<string, ?CarbonImmutable>>
     */
    private array $asignados = [];

    public function __construct(private readonly Session $sesion) {}

    /**
     * Roles que el usuario tiene vigentes, en el orden del enum: del más
     * amplio al más específico.
     *
     * @return list<Rol>
     */
    public function disponibles(User $usuario): array
    {
        $vigencias = $this->vigencias($usuario);

        return array_values(array_filter(
            Rol::cases(),
            static fn (Rol $rol): bool => array_key_exists($rol->value, $vigencias),
        ));
    }

    /**
     * Los que tiene sin fecha de fin. Son su puesto de siempre; lo temporal
     * es la excepción.
     *
     * @return list<Rol>
     */
    public function permanentes(User $usuario): array
    {
        $vigencias = $this->vigencias($usuario);

        return array_values(array_filter(
            $this->disponibles($usuario),
            static fn (Rol $rol): bool => $vigencias[$rol->value] === null,
        ));
    }

    public function esTemporal(User $usuario, Rol $rol): bool
    {
        return $this->vigenciaDe($usuario, $rol) !== null;
    }

    /** Hasta cuándo vale el rol. Null si es permanente o si no lo tiene. */
    public function vigenciaDe(User $usuario, Rol $rol): ?CarbonImmutable
    {
        return $this->vigencias($usuario)[$rol->value] ?? null;
    }

    /**
     * El rol activo. Si la sesión no trae ninguno, o trae uno que el usuario
     * ya no tiene, se cae a su rol permanente.
     *
     * Se prefiere el permanente al temporal aunque el enum lo ponga después,
     * y es deliberado: la elevación es excepcional. Quien recibe el rol de
     * coordinador para aprobar mientras la coordinadora está en consejo
     * sigue entrando a hacer su trabajo de siempre, y se pasa al rol
     * delegado cuando le toca aprobar algo.
     *
     * Null solo si el usuario no tiene ningún rol vigente, que es un caso de
     * datos incompletos, no de uso normal.
     */
    public function actual(User $usuario): ?Rol
    {
        $disponibles = $this->disponibles($usuario);

        if ($disponibles === []) {
            return null;
        }

        $enSesion = Rol::tryFrom((string) $this->sesion->get(self::CLAVE_DE_SESION));

        if (in_array($enSesion, $disponibles, true)) {
            return $enSesion;
        }

        return $this->permanentes($usuario)[0] ?? $disponibles[0];
    }

    /**
     * Si la sesión trae un rol que el usuario ya no tiene vigente: se lo
     * revocaron o le venció mientras tenía el panel abierto.
     *
     * actual() resuelve ese caso cayendo a otro rol, que es lo correcto al
     * navegar. Una acción desde una pantalla ya abierta es distinta: se
     * pidió con el rol que la abrió, y ejecutarla con otro sería actuar en
     * nombre de un rol que el usuario no eligió.
     */
    public function seRetiroElDeLaSesion(User $usuario): bool
    {
        $enSesion = Rol::tryFrom((string) $this->sesion->get(self::CLAVE_DE_SESION));

        return $enSesion !== null && ! in_array($enSesion, $this->disponibles($usuario), true);
    }

    /**
     * Guarda el rol elegido. Devuelve false si el usuario no lo tiene
     * vigente: nadie asume un rol que no le corresponde, ni uno que ya
     * venció, venga la petición de donde venga.
     */
    public function establecer(User $usuario, Rol $rol): bool
    {
        if (! in_array($rol, $this->disponibles($usuario), true)) {
            return false;
        }

        $this->sesion->put(self::CLAVE_DE_SESION, $rol->value);

        return true;
    }

    /**
     * Deja al usuario con un único rol cargado durante esta petición.
     *
     * spatie/laravel-permission resuelve hasRole() sobre la relación roles
     * ya cargada (loadMissing). Al dejar ahí solo el rol activo, todas las
     * Policies y Gates del proyecto pasan a evaluarse contra él sin que
     * ninguna tenga que enterarse de que existe un selector de rol.
     *
     * No toca la base de datos: los roles asignados siguen intactos.
     */
    public function aplicar(User $usuario, Rol $rol): void
    {
        // Se consulta por la relación, no por la colección ya cargada: esa
        // puede venir recortada de una llamada anterior sobre la misma
        // instancia, y entonces el rol buscado ni siquiera estaría ahí.
        $usuario->setRelation('roles', $usuario->roles()->where('name', $rol->value)->get());
    }

    public function olvidar(): void
    {
        $this->sesion->forget(self::CLAVE_DE_SESION);
    }

    /**
     * Tira lo que se leyó de este usuario en esta petición.
     *
     * Esta clase vive una petición entera (scoped), así que si sus roles
     * cambian a mitad —el ADMIN revoca desde la pantalla— lo cacheado se
     * queda viejo hasta la siguiente. Quien los cambia avisa por aquí.
     */
    public function olvidarAsignados(User $usuario): void
    {
        unset($this->asignados[$usuario->id]);
    }

    /**
     * @return array<string, ?CarbonImmutable> nombre del rol => fecha de fin
     */
    private function vigencias(User $usuario): array
    {
        return $this->asignados[$usuario->id] ??= $this->consultarVigencias($usuario);
    }

    /**
     * @return array<string, ?CarbonImmutable>
     */
    private function consultarVigencias(User $usuario): array
    {
        $vigencias = [];

        foreach ($usuario->roles()->get() as $role) {
            /** @var Role $role */
            $hasta = $role->getRelationValue('pivot')->hasta;

            $vigencias[$role->name] = $hasta?->toImmutable();
        }

        return $vigencias;
    }
}
