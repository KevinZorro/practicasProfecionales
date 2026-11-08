@extends('layouts.panel')

@section('titulo', 'Depuración de datos')
@section('descripcion', 'Los formatos firmados, las evaluaciones y la bitácora se conservan dos semestres. Lo más viejo lo depuras tú.')

@section('contenido')
    <livewire:retencion.depuracion-de-datos />
@endsection
