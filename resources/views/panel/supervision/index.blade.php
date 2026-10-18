@extends('layouts.panel')

@section('titulo', 'Evaluaciones de mis docentes')
@section('descripcion', 'Los resultados de las evaluaciones que hicieron los docentes a tu cargo.')

@section('contenido')
    <livewire:supervision.evaluaciones-de-mis-docentes />
@endsection
