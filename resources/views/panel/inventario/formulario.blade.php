@extends('layouts.panel')

@section('titulo', $item ? 'Editar ítem' : 'Registrar ítem')

@section('contenido')
    <livewire:inventario.formulario-item :item="$item" />
@endsection
