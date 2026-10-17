@extends('layouts.panel')

@section('titulo', $solicitud->casoClinico->nombre)
@section('sin-cabecera', '1')

@section('contenido')
    <x-expediente :solicitud="$solicitud" :volver="$volver" pestana="detalle">
        <livewire:solicitud.expediente-de-solicitud :solicitud="$solicitud" />
    </x-expediente>
@endsection
