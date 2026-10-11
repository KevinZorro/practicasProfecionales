{{--
    Lo que se ve cuando no hay nada: qué irá aquí y, si se puede, cómo
    empezar. Plano, sobre el fondo del panel, sin caja punteada.
--}}
@props(['titulo' => 'No hay nada que mostrar', 'descripcion' => null])

<div {{ $attributes->class('rounded-2xl bg-white px-6 py-10 text-center') }}>
    <svg class="mx-auto mb-3 size-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
    </svg>
    <p class="text-[15px] font-semibold text-gray-900">{{ $titulo }}</p>

    @if ($descripcion)
        <p class="mx-auto mt-1 max-w-[48ch] text-[14px] leading-snug text-gray-500">{{ $descripcion }}</p>
    @endif

    @if (trim($slot) !== '')
        <div class="mt-5 flex justify-center">{{ $slot }}</div>
    @endif
</div>
