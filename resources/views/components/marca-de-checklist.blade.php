{{--
    Un ítem del checklist ya cerrado: una caja de tinta con la marca si se
    cumplió, hueca si no. Reemplaza la casilla deshabilitada del navegador,
    que sale gris sobre gris y no se lee. El texto para el lector de
    pantalla va en el ítem.
--}}
@props(['cumplido'])

@if ($cumplido)
    <span {{ $attributes->class('mt-px flex size-[1.125rem] shrink-0 items-center justify-center rounded bg-gray-900 text-white') }} aria-hidden="true">
        <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
    </span>
@else
    <span {{ $attributes->class('mt-px size-[1.125rem] shrink-0 rounded border-2 border-gray-400 bg-white') }} aria-hidden="true"></span>
@endif
