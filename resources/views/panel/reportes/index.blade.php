@extends('layouts.panel')

@section('titulo', 'Reportes')
@section('descripcion', 'Uso de escenarios y resultados de evaluación, en pantalla, Excel y PDF.')

@section('contenido')
    <livewire:reportes.pantalla-de-reportes />
@endsection
