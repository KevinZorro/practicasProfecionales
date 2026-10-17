@extends('layouts.panel')

@section('titulo', 'Formato intramural · '.$solicitud->casoClinico->nombre)
@section('sin-cabecera', '1')

@section('contenido')
    <x-expediente :solicitud="$solicitud" :volver="$volver" pestana="formato-intramural">
        <livewire:solicitud.formato-intramural :solicitud="$solicitud" />
    </x-expediente>
@endsection
