<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

/**
 * El permiso de la pantalla se vuelve a comprobar en cada petición del
 * componente, no solo al abrirla.
 *
 * Las acciones, los filtros y la paginación de una pantalla ya abierta van a
 * /livewire/update, que no pasa por el controlador que la autorizó. Si la
 * persona cambia de rol activo en otra pestaña y el rol nuevo sigue
 * asignado, EstablecerRolActivo no corta —el rol es legítimo— y la
 * pantalla seguiría pintando datos que ese rol no puede ver. Aquí se corta.
 *
 * Livewire llama a bootedAutorizaEnCadaPeticion() en cada petición: al
 * abrirse, después de mount(), y en las siguientes, con las propiedades ya
 * hidratadas. Siempre antes de cualquier acción y del render.
 *
 * autorizarPantalla() pide el mismo permiso que el controlador de la página.
 * ComponentesAutorizanEnCadaPeticionTest falla si un componente no usa este
 * trait.
 */
trait AutorizaEnCadaPeticion
{
    abstract protected function autorizarPantalla(): void;

    public function bootedAutorizaEnCadaPeticion(): void
    {
        $this->autorizarPantalla();
    }
}
