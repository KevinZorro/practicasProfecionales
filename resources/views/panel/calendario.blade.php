@extends('layouts.panel')

@section('titulo', 'Calendario de escenarios')

@section('contenido')
    <div class="space-y-4">
        <p class="flex flex-wrap items-center gap-4 text-sm text-gray-600">
            <span class="inline-flex items-center gap-2">
                <span class="size-3 rounded-sm" style="background:#0369a1"></span> Práctica
            </span>
            <span class="inline-flex items-center gap-2">
                <span class="size-3 rounded-sm" style="background:#7e22ce"></span> Evaluación
            </span>
            <span class="text-gray-500">Solo se muestran los escenarios aprobados.</span>
        </p>

        <x-tarjeta>
            <div id="calendario" data-eventos="{{ route('panel.calendario.eventos') }}"></div>
        </x-tarjeta>

        <div id="detalle-evento" hidden>
            <x-tarjeta titulo="Detalle del escenario">
                <p data-titulo class="mb-3 text-base font-semibold text-gray-900"></p>
                <dl data-cuerpo class="grid grid-cols-2 gap-3 sm:grid-cols-4"></dl>
            </x-tarjeta>
        </div>
    </div>

    {{--
        FullCalendar trae su propio marcado y sus propias clases, así que su
        barra superior no se puede acomodar con utilidades de Tailwind desde
        aquí. Es el caso que el CLAUDE.md admite para CSS suelto: apilarla en
        pantalla estrecha, donde los tres grupos no caben en una fila.
    --}}
    <style>
        @media (max-width: 640px) {
            /* Los tres grupos de la barra no caben en una fila a 390 px. */
            .fc .fc-toolbar.fc-header-toolbar {
                flex-direction: column;
                align-items: stretch;
                gap: .5rem;
            }
            .fc .fc-toolbar-title { font-size: 1.05rem; text-align: center; }

            /* La rejilla sí necesita más ancho del que hay: se desplaza en
               horizontal, igual que las tablas del sistema, en vez de
               recortar el texto de los eventos. La barra se queda fuera del
               desplazamiento para que el título siga legible. */
            .fc .fc-view-harness { overflow-x: auto; }
            .fc .fc-view-harness > .fc-view { min-width: 36rem; }
        }
    </style>

    @vite('resources/js/calendario.js')
@endsection
