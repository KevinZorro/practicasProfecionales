@extends('layouts.panel')

@section('titulo', 'Evaluaciones')
@section('descripcion', 'Las evaluaciones de habilidades, sobre sesiones de evaluación ya aprobadas.')

@section('contenido')
    <livewire:evaluacion.evaluaciones />
@endsection
