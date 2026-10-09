@extends('layouts.panel')

@section('titulo', 'Participantes de la sesión')

@section('contenido')
    <livewire:solicitud.participantes-de-la-sesion :solicitud="$solicitud" />
@endsection
