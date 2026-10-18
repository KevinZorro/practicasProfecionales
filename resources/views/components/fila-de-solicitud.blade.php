{{--
    Una solicitud en una lista: la fecha a la izquierda para recorrerlas por
    día, el escenario con quién y cuándo, y a la derecha la línea de estados
    en pequeño, en una columna de ancho fijo para compararlas de un vistazo. Toda la fila lleva al expediente (el enlace del título se
    estira sobre ella), y lo que haya que advertir va en el slot.

    La solicitud llega con docente, materia, casoClinico y preparacion.sala
    ya cargados: la lista no hace una consulta por fila.
--}}
@props(['solicitud', 'conDocente' => true])

@php
    $sala = $solicitud->preparacion?->sala;
    $detalles = array_filter([
        substr($solicitud->hora_inicio, 0, 5).'–'.substr($solicitud->hora_fin, 0, 5),
        trans_choice(':count estudiante|:count estudiantes', $solicitud->cantidad_estudiantes, ['count' => $solicitud->cantidad_estudiantes]),
        $solicitud->grupo ? 'Grupo '.$solicitud->grupo : null,
        $sala?->nombre,
    ]);
@endphp

<li {{ $attributes->class('relative grid grid-cols-[3rem_minmax(0,1fr)] gap-x-4 gap-y-2.5 px-4 py-4 transition-colors duration-150 hover:bg-gray-100 sm:grid-cols-[3rem_minmax(0,1fr)_12.5rem] sm:items-center sm:px-5') }}>
    <div class="row-span-2 text-center sm:row-span-1" aria-hidden="true">
        <span class="block text-[12px] font-semibold text-gray-600">{{ $solicitud->fecha->translatedFormat('M') }}</span>
        <span class="block text-[22px] font-bold leading-none tracking-[-0.02em] text-gray-900">{{ $solicitud->fecha->format('j') }}</span>
    </div>

    <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1">
            <a href="{{ route('panel.solicitudes.ver', $solicitud) }}"
               class="min-w-0 text-[15px] font-semibold leading-snug text-gray-900 after:absolute after:inset-0 after:rounded-[inherit] focus-visible:outline-none focus-visible:after:outline focus-visible:after:outline-2 focus-visible:after:-outline-offset-2 focus-visible:after:outline-rojo">
                {{ $solicitud->casoClinico->nombre }}
                <span class="sr-only">, {{ $solicitud->fecha->translatedFormat('j \d\e F') }}</span>
            </a>
            <x-tipo-de-sesion :tipo="$solicitud->tipo" />
        </div>
        <p class="mt-0.5 text-pretty text-[14px] leading-snug text-gray-600">
            @if ($conDocente)
                {{ ($solicitud->docenteQueDicta ?? $solicitud->docente)->nombre }} ·
            @endif
            {{ $solicitud->materia->nombre }}
        </p>
        <p class="mt-0.5 text-[13px] leading-snug text-gray-500">{{ implode(' · ', $detalles) }}</p>
        @if (trim($slot) !== '')
            <div class="mt-1.5 space-y-1 text-[13px] leading-snug">{{ $slot }}</div>
        @endif
    </div>

    {{-- Columna fija: las líneas de todas las filas caen en la misma x. --}}
    <x-linea-de-estados :solicitud="$solicitud" compacta class="col-start-2 sm:col-start-auto" />
</li>
