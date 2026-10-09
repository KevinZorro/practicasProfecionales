@extends('layouts.panel')

@section('titulo', $cuenta ? 'Editar cuenta' : 'Nueva cuenta')

@section('contenido')
    <livewire:usuario.formulario-de-cuenta :cuenta="$cuenta" />
@endsection
