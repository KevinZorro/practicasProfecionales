@extends('layouts.panel')

@section('titulo', $item ? 'Editar ítem' : 'Registrar ítem')

@section('volver')
    <a href="{{ route('panel.inventario') }}" class="enlace-de-vuelta">
        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
        Inventario
    </a>
@endsection

@section('contenido')
    <livewire:inventario.formulario-item :item="$item" />
@endsection
