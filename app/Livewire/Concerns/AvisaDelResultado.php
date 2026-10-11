<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

/**
 * El aviso de lo que acaba de pasar («Solicitud aprobada»), que el layout
 * del panel enseña arriba durante unos segundos.
 *
 * Va por dos caminos a la vez porque la pantalla puede quedarse o irse:
 *
 * - Si se queda, el layout no se vuelve a pintar (Livewire solo repinta el
 *   componente), así que el aviso llega como evento del navegador.
 * - Si redirige, llega en la sesión, y el layout de la página siguiente lo
 *   pinta al cargar.
 *
 * Cuando la pantalla se queda, Livewire borra lo guardado en la sesión al
 * terminar la petición: el aviso no reaparece en la página siguiente.
 */
trait AvisaDelResultado
{
    protected function avisar(string $mensaje): void
    {
        session()->flash('estado', $mensaje);
        $this->dispatch('aviso-de-la-accion', mensaje: $mensaje);
    }
}
