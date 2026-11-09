{{--
    Sin permiso. El mensaje dice el porqué cuando se sabe: una cuenta sin
    rol, una cuenta inactiva, un rol que venció con la pantalla abierta.
    Quien tiene otro rol se entera de que puede cambiarlo: con el rol
    equivocado puesto, el 403 parecía un error del sistema. Y todos, de
    cómo escribir o llamar al laboratorio: con un 403 la base responde, así
    que aquí sí se puede consultar.
--}}
@extends('errors::minimal')

@section('title', 'No puedes ver esto')
@section('code', '403')
@section('message', __($exception->getMessage() ?: 'This action is unauthorized.'))

@section('ayuda')
    @if (count($rolesDisponibles ?? []) > 1)
        <p class="mb-2">Tienes otro rol en la plataforma. Si esto le corresponde, cámbialo en el panel, arriba a la derecha, y vuelve a intentarlo.</p>
    @endif
    <p><x-contacto-del-laboratorio>¿Crees que es un error?</x-contacto-del-laboratorio></p>
@endsection
