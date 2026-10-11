@extends('layouts.panel')

@section('titulo', 'Sesiones apartadas')
@section('descripcion', 'Las que coordinación aparta antes del semestre: nacen aprobadas y llevan su formato intramural.')

@section('contenido')
    <livewire:solicitud.sesiones-apartadas />
@endsection
