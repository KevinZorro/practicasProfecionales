@extends('layouts.panel')

@section('titulo', 'Novedades · '.$solicitud->casoClinico->nombre)
@section('sin-cabecera', '1')

@section('contenido')
    <x-expediente :solicitud="$solicitud" pestana="novedades">
        <livewire:solicitud.novedades-de-la-sesion :solicitud="$solicitud" />
    </x-expediente>
@endsection
