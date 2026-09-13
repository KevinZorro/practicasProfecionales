<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Contracts\Session\Session;

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
 */
final class RolActivo
{
    public const CLAVE_DE_SESION = 'rol_activo';

    /**
     * Roles asignados, por usuario, durante esta petición.
     *
     * Hace falta porque aplicar() deja la relación "roles" recortada al rol
     * activo: a partir de ese momento, leerla ya no dice qué roles tiene el
     * usuario, sino cuál está usando. Los dos métodos que necesitan la
     * verdad consultan la base, y esto evita repetir la consulta.
     *
     * @var array<int, list<Rol>>
     */
    private array $asignados = [];

    public function __construct(private readonly Session $sesion) {}

    /**
     * Roles que el usuario tiene asignados de verdad, en el orden del enum:
     * del más amplio al más específico.
     *
     * @return list<Rol>
     */
    public function disponibles(User $usuario): array
    {
        return $this->asignados[$usuario->id] ??= $this->consultarAsignados($usuario);
    }

    /**
     * @return list<Rol>
     */
    private function consultarAsignados(User $usuario): array
    {
        $nombres = $usuario->roles()->pluck('name')->all();

        return array_values(array_filter(
            Rol::cases(),
            static fn (Rol $rol): bool => in_array($rol->value, $nombres, true),
        ));
    }

    /**
     * El rol activo. Si la sesión no trae ninguno, o trae uno que el usuario
     * ya no tiene, se cae al primero de sus roles.
     *
     * Null solo si el usuario no tiene ningún rol asignado, que es un caso
     * de datos incompletos, no de uso normal.
     */
    public function actual(User $usuario): ?Rol
    {
        $disponibles = $this->disponibles($usuario);

        if ($disponibles === []) {
            return null;
        }

        $enSesion = Rol::tryFrom((string) $this->sesion->get(self::CLAVE_DE_SESION));

        return in_array($enSesion, $disponibles, true) ? $enSesion : $disponibles[0];
    }

    /**
     * Guarda el rol elegido. Devuelve false si el usuario no lo tiene
     * asignado: nadie asume un rol que no le corresponde, venga la petición
     * de donde venga.
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
}
