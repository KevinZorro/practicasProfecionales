@extends('layouts.panel')

@section('titulo', 'Insumos por pedir')
@section('descripcion', 'El soporte de la carta de solicitud de compra del semestre.')

@section('contenido')
    <livewire:reposicion.lista-de-reposicion />
@endsection
