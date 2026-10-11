@extends('layouts.panel')

@section('titulo', 'Mis resultados')
@section('descripcion', 'Tus evaluaciones finalizadas, con el checklist que marcó el docente.')

@section('contenido')
    <livewire:evaluacion.mis-resultados />
@endsection
