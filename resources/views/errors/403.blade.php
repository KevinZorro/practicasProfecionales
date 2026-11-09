{{--
    Sin permiso. El mensaje dice el porqué cuando se sabe: una cuenta sin
    rol, una cuenta inactiva, un rol que venció con la pantalla abierta.
    Quien tiene otro rol se entera de que puede cambiarlo: con el rol
    equivocado puesto, el 403 parecía un error del sistema.
--}}
@extends('errors::minimal')

@section('title', 'No puedes ver esto')
@section('code', '403')
@section('message', __($exception->getMessage() ?: 'This action is unauthorized.'))

@if (count($rolesDisponibles ?? []) > 1)
    @section('ayuda')
        Tienes otro rol en la plataforma. Si esto le corresponde, cámbialo en el panel, arriba a la derecha, y vuelve a intentarlo.
    @endsection
@endif
