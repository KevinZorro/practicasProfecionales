@extends('layouts.panel')

@section('titulo', 'Usuarios y roles')
@section('descripcion', 'Las cuentas de la plataforma y sus roles, con o sin fecha de fin.')

@section('contenido')
    <livewire:usuario.roles-de-usuarios />
@endsection
