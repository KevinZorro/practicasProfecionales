@extends('layouts.panel')

@section('titulo', 'Bloqueos de acceso')
@section('descripcion', 'Quién no puede entrar al laboratorio y por qué. Un bloqueo se levanta, no se borra.')

@section('contenido')
    <livewire:bloqueo.gestion-de-bloqueos />
@endsection
