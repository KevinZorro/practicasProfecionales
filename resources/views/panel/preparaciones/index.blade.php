@extends('layouts.panel')

@section('titulo', 'Preparación de escenarios')
@section('descripcion', 'Lo aprobado para el día, por hora: asigna la sala, alista el material y marca el montaje.')

@section('contenido')
    <livewire:preparacion.tablero-diario />

    {{-- El salón que se ve al unir salas (P16). Va antes de que arranque
         Alpine, que lo hace Livewire al terminar de cargar la página. --}}
    @vite('resources/js/salon.js')
@endsection
