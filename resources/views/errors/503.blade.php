@extends('errors::minimal')

@section('title', 'Estamos en mantenimiento')
@section('code', '503')
@section('message', 'La plataforma vuelve en unos minutos. Lo que ya guardaste sigue ahí.')

@section('acciones')
    <x-boton :href="url()->current()">Volver a intentar</x-boton>
@endsection
