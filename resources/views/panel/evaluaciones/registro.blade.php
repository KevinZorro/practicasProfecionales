@extends('layouts.panel')

@section('titulo', 'Evaluación')

@section('contenido')
    <livewire:evaluacion.registro-de-evaluacion :evaluacion="$evaluacion" />
@endsection
