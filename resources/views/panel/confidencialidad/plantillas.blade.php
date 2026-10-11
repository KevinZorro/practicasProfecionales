@extends('layouts.panel')

@section('titulo', 'Plantillas de confidencialidad')
@section('descripcion', 'El documento que descargan quienes firman. Solo hay una versión vigente.')

@section('contenido')
    <livewire:confidencialidad.gestion-de-plantilla />
@endsection
