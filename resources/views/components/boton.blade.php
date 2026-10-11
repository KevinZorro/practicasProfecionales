{{--
    Botón del panel, en pastilla como en la portada.

    - primario: tinta; la acción que avanza la tarea.
    - secundario: blanco con filete; lo que acompaña.
    - peligro: rojo; lo que no se deshace (rechazar, dar de baja). Solo
      para confirmar: el primer clic de una acción así va en peligro-suave.
    - peligro-suave: texto rojo con filete; abre la confirmación.
    - texto: sin fondo; lo de menos peso en una fila.

    Mientras Livewire procesa el clic, el botón queda atenuado
    (data-loading lo pone Livewire en el elemento que lanzó la petición).
--}}
@props([
    'variante' => 'primario',
    'tipo' => 'submit',
    'tamano' => 'normal',
    'href' => null,
])

@php
    $base = 'inline-flex select-none items-center justify-center gap-2 whitespace-nowrap rounded-full font-semibold transition-colors duration-150 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rojo disabled:cursor-not-allowed disabled:opacity-45 data-[loading]:cursor-wait data-[loading]:opacity-60';

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
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->except('type')->class($clases) }}>{{ $slot }}</a>
@else
    <button type="{{ $tipoDelBoton }}" {{ $attributes->except('type')->class($clases) }}>{{ $slot }}</button>
@endif
