@extends('layouts.panel')

@section('titulo', 'Novedades de la sesión')

@section('contenido')
    <livewire:solicitud.novedades-de-la-sesion :solicitud="$solicitud" />
@endsection
