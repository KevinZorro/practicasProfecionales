{{--
    Botón del panel, en pastilla como en la portada.

    - primario: tinta; la acción que avanza la tarea.
    - secundario: blanco con filete; lo que acompaña.
    - peligro: rojo; lo que no se deshace (rechazar, dar de baja). Solo
      para confirmar: el primer clic de una acción así va en peligro-suave.
    - peligro-suave: texto rojo con filete; abre la confirmación.
    - texto: sin fondo; lo de menos peso en una fila.

    Mientras Livewire procesa su acción, el botón queda deshabilitado
    (wire:loading.attr), así un segundo toque no la repite (QA-04). Livewire
    acota ese estado a la acción del propio botón: la de su wire:click, o la
    que diga su wire:target en un formulario.

    Con «cargando» («Aprobando…»), mientras espera dice qué está haciendo,
    con un aro que gira, y se ve entero en vez de apagado: con mala señal es
    lo que evita la duda de si el toque llegó (DESIGN.md, «Respuesta al
    tocar»). Al presionarlo se encoge un poco, salvo con movimiento reducido,
    que además deja quieto el aro.
--}}
@props([
    'variante' => 'primario',
    'tipo' => 'submit',
    'tamano' => 'normal',
    'href' => null,
    'cargando' => null,
])

@php
    $base = 'inline-flex select-none items-center justify-center gap-2 whitespace-nowrap rounded-full font-semibold transition-[color,background-color,box-shadow,opacity,transform] duration-150 motion-safe:active:scale-[0.97] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rojo disabled:cursor-not-allowed disabled:opacity-45';

    $tamanos = [
        'normal' => 'min-h-11 px-5 text-[15px] sm:min-h-10',
        'chico' => 'min-h-9 px-3.5 text-[13px] sm:min-h-8',
    ];

    $variantes = [
        'primario' => 'bg-gray-900 text-white hover:bg-black',
        'secundario' => 'bg-white text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 hover:ring-gray-400',
        'peligro' => 'bg-rojo text-white hover:bg-rojo-hondo',
        'peligro-suave' => 'bg-white text-rojo-700 ring-1 ring-inset ring-rojo-200 hover:bg-rojo-50',
        'texto' => 'text-gray-700 hover:bg-gray-900/[0.06] hover:text-gray-900',
    ];

    $clases = $base.' '.($tamanos[$tamano] ?? $tamanos['normal']).' '.($variantes[$variante] ?? $variantes['primario']);

    // Se acepta también type="button": era la forma de llamarlo en las
    // pantallas, y repetir el atributo dejaba el primero, el de enviar.
    $tipoDelBoton = $attributes->get('type', $tipo);

    // La acción que espera: la de su wire:click o, si envía un formulario, la de su wire:target.
    $accion = $attributes->get('wire:click') ?? $attributes->get('wire:target');
    $diceQueTrabaja = $cargando !== null && $accion !== null;
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->except('type')->class($clases) }}>{{ $slot }}</a>
@else
    {{-- Fuera de Livewire, los wire:loading no hacen nada. --}}
    <button type="{{ $tipoDelBoton }}" wire:loading.attr="disabled"
            @if ($diceQueTrabaja) wire:loading.class="cursor-progress !opacity-100" @endif
            {{ $attributes->except('type')->class($clases) }}>
        @if ($diceQueTrabaja)
            <span wire:loading.inline-block wire:target="{{ $accion }}" aria-hidden="true"
                  class="size-4 shrink-0 rounded-full border-2 border-current border-r-transparent motion-safe:animate-spin"></span>
            <span wire:loading.remove wire:target="{{ $accion }}">{{ $slot }}</span>
            <span wire:loading wire:target="{{ $accion }}">{{ $cargando }}</span>
        @else
            {{ $slot }}
        @endif
    </button>
@endif
