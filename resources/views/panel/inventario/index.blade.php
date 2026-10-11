@extends('layouts.panel')

@section('titulo', 'Inventario')
@section('descripcion', 'Simuladores, equipo clínico y equipo básico, con sus unidades por estado funcional.')

@section('acciones')
    <x-boton variante="secundario" href="{{ route('panel.inventario.disponibilidad') }}">Disponibilidad</x-boton>
    @can('create', \App\Models\ItemInventario::class)
        <x-boton href="{{ route('panel.inventario.nuevo') }}">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            Registrar ítem
        </x-boton>
    @endcan
@endsection

@section('contenido')
    <livewire:inventario.listado-inventario />
@endsection
