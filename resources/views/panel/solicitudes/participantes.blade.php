@extends('layouts.panel')

@section('titulo', 'Participantes · '.$solicitud->casoClinico->nombre)
@section('sin-cabecera', '1')

@section('contenido')
    <x-expediente :solicitud="$solicitud" pestana="participantes">
        <livewire:solicitud.participantes-de-la-sesion :solicitud="$solicitud" />
    </x-expediente>
@endsection
