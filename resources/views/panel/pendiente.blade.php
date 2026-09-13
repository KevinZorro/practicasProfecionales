{{-- Marcador de posición mientras la pantalla del módulo no existe. --}}
@extends('layouts.panel')

@section('titulo', $seccion->etiqueta)

@section('contenido')
    <x-mensaje-vacio
        titulo="Esta sección todavía no tiene pantalla"
        :descripcion="'El armazón del panel ya está en pie; «'.$seccion->etiqueta.'» llegará con su módulo.'"
    />
@endsection
