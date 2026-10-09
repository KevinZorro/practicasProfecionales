@extends('layouts.panel')

@section('titulo', 'Formato intramural')

@section('contenido')
    <livewire:solicitud.formato-intramural :solicitud="$solicitud" />
@endsection
