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

    public function darDeBaja(User $usuario, ItemInventario $item): bool
    {
        return $this->gestionaInventario($usuario);
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
