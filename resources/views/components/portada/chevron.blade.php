@props(['direccion' => 'derecha'])

<svg {{ $attributes->class('inline-block size-[0.7em] shrink-0') }} viewBox="0 0 12 12" fill="none" aria-hidden="true">
    @if ($direccion === 'izquierda')
        <path d="M7.5 2L3.5 6l4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
    @else
        <path d="M4.5 2l4 4-4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
    @endif
</svg>
