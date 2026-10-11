{{--
    Aviso dentro de una pantalla: qué pasa y, si hace falta, qué hacer.

    El tono lo dice el ícono además del color, porque el color solo no basta
    para quien no lo percibe. Sin franja lateral: el fondo claro y el ícono
    ya separan el aviso del resto.
--}}
@props(['tono' => 'info'])

@php
    $tonos = [
        'info' => ['bg-gray-100 text-gray-800', 'text-gray-500', 'M12 16v-4m0-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        'exito' => ['bg-emerald-50 text-emerald-900', 'text-emerald-600', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
        'atencion' => ['bg-amber-50 text-amber-900', 'text-amber-600', 'M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z'],
        'peligro' => ['bg-rojo-50 text-rojo-800', 'text-rojo', 'M12 9v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
    ];
    [$fondo, $tonoIcono, $icono] = $tonos[$tono] ?? $tonos['info'];
@endphp

<div {{ $attributes->class("flex gap-3 rounded-xl px-4 py-3 text-[14px] leading-snug {$fondo}") }}>
    <svg class="mt-px size-[18px] shrink-0 {{ $tonoIcono }}" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icono }}"/>
    </svg>
    <div class="min-w-0 flex-1">{{ $slot }}</div>
</div>
