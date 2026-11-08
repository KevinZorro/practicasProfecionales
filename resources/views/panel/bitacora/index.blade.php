@extends('layouts.panel')

@section('titulo', 'Bitácora')
@section('descripcion', 'Quién aprobó, rechazó, reprogramó, sustituyó, retiró, bloqueó o cambió roles, y cuándo. Se conserva dos semestres; lo más viejo lo depura el ADMIN.')

@section('contenido')
    <livewire:bitacora.consulta-de-bitacora />
@endsection
