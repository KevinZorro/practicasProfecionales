@extends('layouts.panel')

@section('titulo', 'Cambiar docente o fecha · '.$solicitud->casoClinico->nombre)
@section('sin-cabecera', '1')

@section('contenido')
    <x-expediente :solicitud="$solicitud" :volver="$volver" pestana="novedades">
        <livewire:solicitud.novedades-de-la-sesion :solicitud="$solicitud" />
    </x-expediente>
@endsection
