{{--
    Un bloque de la pantalla «Hoy» con solicitudes: el título con cuántas
    hay, las primeras filas (cada una abre su expediente) y el enlace a la
    lista completa. Vacío, dice que no hay nada: también es una respuesta.

    «decide» marca los bloques que esperan una decisión de quien mira: su
    cifra va en rojo, el acento de lo que está en juego.
--}}
@props(['titulo', 'solicitudes', 'vacio', 'enlace' => null, 'conDocente' => true, 'decide' => false])

@php
    $total = $solicitudes instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $solicitudes->total() : $solicitudes->count();
@endphp

<section {{ $attributes->class('min-w-0 overflow-hidden rounded-2xl bg-white') }}>
    <header class="flex items-center justify-between gap-3 px-4 pt-4 sm:px-5 sm:pt-5">
        <h2 class="flex items-center gap-2 text-[17px] font-bold leading-snug tracking-[-0.01em] text-gray-900">
            {{ $titulo }}
            @if ($total > 0)
                <span @class([
                    'inline-flex h-6 min-w-6 items-center justify-center rounded-full px-1.5 text-[12px] font-semibold',
                    'bg-rojo text-white' => $decide,
                    'bg-gray-100 text-gray-700' => ! $decide,
                ])>{{ $total }}</span>
            @endif
        </h2>
        @if ($enlace && $total > 0)
            <a href="{{ $enlace }}" class="shrink-0 rounded-full px-2 py-1 text-[14px] font-semibold text-gray-700 hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rojo">
                Ver {{ $total > $solicitudes->count() ? 'las '.$total : 'todas' }}
            </a>
        @endif
    </header>

    @if ($total === 0)
        <p class="flex items-center gap-2 px-4 pb-5 pt-2 text-[14px] text-gray-500 sm:px-5">
            <svg class="size-4 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
            {{ $vacio }}
        </p>
        @if (trim($slot) !== '')
            <div class="px-4 pb-5 sm:px-5">{{ $slot }}</div>
        @endif
    @else
        <ul class="mt-3 divide-y divide-gray-100 border-t border-gray-100">
            @foreach ($solicitudes as $solicitud)
                <x-fila-de-solicitud :solicitud="$solicitud" :con-docente="$conDocente" wire:key="hoy-{{ $solicitud->id }}" />
            @endforeach
        </ul>
    @endif
</section>
