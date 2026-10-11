@extends('layouts.panel')

@section('titulo', 'Solicitudes')
@section('descripcion', 'Por la fecha de la práctica: lo que ocurre antes se atiende antes. Cada solicitud se revisa y se aprueba desde su expediente.')

@section('contenido')
    <livewire:solicitud.bandeja-revision />
@endsection
