@extends('layouts.panel')

@section('titulo', 'Preparación de escenarios')
@section('descripcion', 'Lo aprobado para el día, por hora: asigna la sala, alista el material y marca el montaje.')

@section('contenido')
    <livewire:preparacion.tablero-diario />
@endsection
