@extends('layouts.panel')

@section('titulo', 'Bitácora')
@section('descripcion', 'Quién aprobó, rechazó, reprogramó, sustituyó, retiró, bloqueó o cambió roles, y cuándo. No se borra.')

@section('contenido')
    <livewire:bitacora.consulta-de-bitacora />
@endsection
