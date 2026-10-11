@extends('layouts.panel')

@section('titulo', 'Calendario de escenarios')
@section('descripcion', 'Los escenarios aprobados. Toca uno para ver su detalle.')

@section('contenido')
    <div class="space-y-5">
        <p class="flex flex-wrap items-center gap-2 text-[14px] text-gray-500">
            <x-tipo-de-sesion :tipo="\App\Enums\TipoSesion::Practica" />
            <x-tipo-de-sesion :tipo="\App\Enums\TipoSesion::Evaluacion" />
        </p>

        <x-tarjeta>
            <div id="calendario" class="calendario" data-eventos="{{ route('panel.calendario.eventos') }}"></div>
        </x-tarjeta>

        <div id="detalle-evento" hidden>
            <x-tarjeta titulo="Detalle del escenario">
                <p data-titulo class="mb-4 text-[17px] font-semibold text-gray-900"></p>
                <dl data-cuerpo class="grid grid-cols-2 gap-4 sm:grid-cols-4"></dl>
            </x-tarjeta>
        </div>
    </div>

    @vite('resources/js/calendario.js')
@endsection
