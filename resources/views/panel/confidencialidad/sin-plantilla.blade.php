@extends('layouts.panel')

@section('titulo', 'Formato de confidencialidad')
@section('descripcion', 'Todavía no se puede descargar.')

@section('contenido')
    <x-mensaje-vacio titulo="No hay un formato para descargar" :descripcion="$mensaje" />
@endsection
