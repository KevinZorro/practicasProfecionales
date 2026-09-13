{{--
    Etiqueta de estado. Recibe el enum del dominio, no una cadena: así el
    color y el texto salen del mismo sitio que el estado y no hay forma de
    pintar un estado que no existe.
--}}
@props(['estado'])

@php
    $tono = match ($estado->value) {
        'aprobada', 'verificado', 'finalizada', 'preparado', 'disponible' => 'bg-emerald-50 text-emerald-800 ring-emerald-600/20',
        'rechazada', 'no_aprobado', 'baja' => 'bg-rose-50 text-rose-800 ring-rose-600/20',
        'en_preparacion', 'revisada', 'cargado', 'mantenimiento' => 'bg-amber-50 text-amber-800 ring-amber-600/20',
        default => 'bg-gray-100 text-gray-700 ring-gray-500/20',
    };
@endphp

<span {{ $attributes->class("inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {$tono}") }}>
    {{ method_exists($estado, 'etiqueta') ? $estado->etiqueta() : $estado->value }}
</span>
