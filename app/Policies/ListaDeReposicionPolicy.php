<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\ListaDeReposicion;
use App\Models\User;

/**
 * Lista de insumos por pedir o reponer (RF67).
 *
 * | Acción                     | ADMIN | Coordinador | Administrativo | Docente | Estudiante |
 * | Ver y preparar la lista    |   ✓   |      ✓      |       ✓        |         |            |
 * | Cerrar la lista            |   ✓   |      ✓      |                |         |            |
 *
 * Cerrarla es irreversible y produce el soporte de una carta institucional
 * que firma y presenta coordinación, así que es suya, igual que dar de baja
 * unidades averiadas (RF66). El administrativo prepara la lista y anota lo
 * que hizo falta durante todo el semestre.
 *
 * PENDIENTE de confirmar con el cliente: si prefiere que el administrativo
 * también pueda cerrarla, cerrar() vuelve a prepararLaLista().
 *
 * Ojo con el Gate "generarReportes" del RF54-RF56: ese es de coordinación y
 * ADMIN, y esta lista la trabaja también el administrativo, así que no se
 * reutiliza.
 *
 * El nombre importa: Laravel resuelve las Policies por modelo, así que la de
 * ListaDeReposicion tiene que llamarse ListaDeReposicionPolicy.
 */
final class ListaDeReposicionPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $this->preparaLaLista($usuario);
    }

    public function view(User $usuario, ListaDeReposicion $lista): bool
    {
        return $this->preparaLaLista($usuario);
    }

    public function create(User $usuario): bool
    {
        return $this->preparaLaLista($usuario);
    }

    public function cerrar(User $usuario, ListaDeReposicion $lista): bool
    {
        return $usuario->hasAnyRole([Rol::Admin->value, Rol::Coordinador->value]);
    }

    /** Descargar el soporte en Excel o PDF: quien puede verla. */
    public function exportar(User $usuario, ListaDeReposicion $lista): bool
    {
        return $this->preparaLaLista($usuario);
    }

    private function preparaLaLista(User $usuario): bool
    {
        return $usuario->hasAnyRole([
            Rol::Admin->value,
            Rol::Coordinador->value,
            Rol::Administrativo->value,
        ]);
    }
}
