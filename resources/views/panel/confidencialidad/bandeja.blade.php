@extends('layouts.panel')

@section('titulo', 'Verificación de formatos de confidencialidad')
@section('descripcion', 'Los formatos que suben estudiantes y docentes, para leerlos y verificarlos.')

@section('contenido')
    <livewire:confidencialidad.bandeja-verificacion />
@endsection
