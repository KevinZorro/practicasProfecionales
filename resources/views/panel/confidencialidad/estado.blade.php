@extends('layouts.panel')

@section('titulo', 'Formatos de confidencialidad al día')
@section('descripcion', 'Quién tiene el formato al día en el periodo y a quién le falta.')

@section('volver')
    <a href="{{ route('panel.formatos-confidencialidad') }}" class="enlace-de-vuelta">
        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
        Verificación de formatos
    </a>
@endsection

@section('contenido')
    <livewire:confidencialidad.estado-de-firmantes />
@endsection
