@extends('layouts.panel')

@section('titulo', 'Mis solicitudes')
@section('descripcion', 'Las que pediste y las que dictas en reemplazo de otro docente.')

@section('acciones')
    <x-boton href="{{ route('panel.solicitudes.nueva') }}">
        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        Solicitar escenario
    </x-boton>
@endsection

@section('contenido')
    <livewire:solicitud.mis-solicitudes />
@endsection
