{{--
    Insignia de estado: una palabra o dos sobre un fondo suave, con un punto
    que repite el tono para leerlo de lejos. El texto lo dice para quien no
    distingue el color. Para los estados del dominio, x-etiqueta-estado
    elige el tono a partir del enum.
--}}
@props(['tono' => 'neutro'])

@php
    [$fondo, $punto] = match ($tono) {
        'exito' => ['bg-emerald-50 text-emerald-800', 'bg-emerald-500'],
        'atencion' => ['bg-amber-50 text-amber-900', 'bg-amber-500'],
        'peligro' => ['bg-rojo-50 text-rojo-800', 'bg-rojo'],
        default => ['bg-gray-100 text-gray-700', 'bg-gray-400'],
    };
@endphp

<span {{ $attributes->class("inline-flex h-6 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 text-[12px] font-semibold {$fondo}") }}>
    <span class="size-1.5 shrink-0 rounded-full {{ $punto }}" aria-hidden="true"></span>
    {{ $slot }}
</span>
