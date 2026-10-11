@extends('layouts.panel')

@section('titulo', 'Periodo académico')
@section('descripcion', 'El laboratorio abre el periodo, y desde ahí cuenta el formato de confidencialidad.')

@section('contenido')
    <livewire:periodo-academico.gestion-de-periodos />
@endsection
