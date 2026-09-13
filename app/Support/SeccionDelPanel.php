<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * Una entrada de la navegación lateral.
 *
 * No sabe nada de roles. Lleva el permiso que hay que consultar, y quien
 * decide es la Policy o el Gate que ya existe. Añadir una sección nueva es
 * añadir una fila a MenuDelPanel, nunca escribir un condicional de rol.
 */
final readonly class SeccionDelPanel
{
    /**
     * @param  string  $ruta  Nombre de la ruta a la que apunta.
     * @param  string|null  $permiso  Ability que se consulta con can(). Null: visible para cualquiera que entre al panel.
     * @param  class-string|null  $sobre  Modelo sobre el que se consulta el permiso. Null para un Gate suelto.
     */
    public function __construct(
        public string $clave,
        public string $etiqueta,
        public string $ruta,
        public string $icono,
        public ?string $permiso = null,
        public ?string $sobre = null,
    ) {}

    public function visiblePara(User $usuario): bool
    {
        if ($this->permiso === null) {
            return true;
        }

        return $this->sobre === null
            ? $usuario->can($this->permiso)
            : $usuario->can($this->permiso, $this->sobre);
    }
}
