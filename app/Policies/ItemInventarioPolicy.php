<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\ItemInventario;
use App\Models\User;

/**
 * Permisos del §6.1 del documento de arquitectura:
 *
 * | Acción                                | ADMIN | Coordinador | Administrativo | Docente | Estudiante |
 * | Registrar y actualizar inventario     |   ✓   |      ✓      |       ✓        |         |            |
 * | Consultar disponibilidad de inventario|   ✓   |      ✓      |       ✓        |         |            |
 * | Registrar nivel de fidelidad          |   ✓   |             |                |         |            |
 * | Cambiar el estado funcional (RF66)    |   ✓   |      ✓      |       ✓        |         |            |
 * | Dar de baja un ítem (RF66)            |   ✓   |      ✓      |                |         |            |
 *
 * El nivel de fidelidad es el único atributo del inventario reservado al
 * ADMIN: la restricción es de campo, no de recurso, así que un
 * administrativo edita el resto de columnas del mismo registro sin problema.
 *
 * El nombre importa: Laravel resuelve las Policies por modelo, así que la
 * de ItemInventario tiene que llamarse ItemInventarioPolicy. Con cualquier
 * otro nombre no se descubre y el Gate deniega en silencio.
 */
final class ItemInventarioPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $this->gestionaInventario($usuario);
    }

    public function view(User $usuario, ItemInventario $item): bool
    {
        return $this->gestionaInventario($usuario);
    }

    public function create(User $usuario): bool
    {
        return $this->gestionaInventario($usuario);
    }

    public function update(User $usuario, ItemInventario $item): bool
    {
        return $this->gestionaInventario($usuario);
    }

    /**
     * RF66: marcar un ítem en revisión, defectuoso o de vuelta a operativo.
     * Es la operación diaria, así que la hace quien gestiona el inventario
     * (RF38).
     */
    public function cambiarEstadoFuncional(User $usuario, ?ItemInventario $item = null): bool
    {
        return $this->gestionaInventario($usuario);
    }

    /**
     * La baja sí pide más que el resto: es la única transición irreversible
     * —saca el ítem del catálogo— y en la operación de hoy el defecto ya se
     * informa a la coordinadora, que es quien decide descartar la pieza. El
     * administrativo llega hasta "defectuoso"; de ahí en adelante decide
     * coordinación.
     *
     * PENDIENTE de confirmar con el cliente: si prefiere que el
     * administrativo también pueda darla, este método vuelve a
     * gestionaInventario() y no hay nada más que tocar.
     */
    public function darDeBaja(User $usuario, ItemInventario $item): bool
    {
        return $usuario->hasAnyRole([Rol::Admin->value, Rol::Coordinador->value]);
    }

    /**
     * RF39. Se consulta desde InventarioService antes de tocar la columna;
     * el modelo, además, la deja fuera de fillable para que ninguna
     * asignación masiva pueda saltarse esta comprobación.
     */
    public function editarNivelFidelidad(User $usuario, ?ItemInventario $item = null): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    /**
     * Los docentes no ven disponibilidad de inventario (RF40), y los
     * estudiantes no tienen nada que hacer aquí.
     */
    private function gestionaInventario(User $usuario): bool
    {
        return $usuario->hasAnyRole([
            Rol::Admin->value,
            Rol::Coordinador->value,
            Rol::Administrativo->value,
        ]);
    }
}
