{{--
    Etiqueta de estado. Recibe el enum del dominio, no una cadena: así el
    tono y el texto salen del mismo sitio que el estado y no hay forma de
    pintar un estado que no existe.
--}}
@props(['estado'])

@php
    $tono = match ($estado->value) {
        'aprobada', 'aprobado', 'verificado', 'finalizada', 'preparado', 'operativo' => 'exito',
        'rechazada', 'no_aprobado', 'defectuoso', 'dado_de_baja' => 'peligro',
        'en_preparacion', 'revisada', 'cargado', 'en_revision' => 'atencion',
        default => 'neutro',
    };
@endphp

<x-insignia :tono="$tono" :attributes="$attributes">
    {{ method_exists($estado, 'etiqueta') ? $estado->etiqueta() : $estado->value }}
</x-insignia>
