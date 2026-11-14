@extends('layouts.panel')

@section('titulo', 'Estado de las salas')
@section('descripcion', 'Lo que se verifica al revisar una solicitud: aire acondicionado, filtraciones, salas que no se pueden usar.')

@section('contenido')
    <livewire:sala.estado-de-salas />
@endsection
