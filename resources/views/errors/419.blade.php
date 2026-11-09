{{--
    La sesión caducó entre que se abrió la pantalla y se envió el
    formulario. Lo útil es volver a la página de la que venía, no a una fija;
    solo si esa página es de este sitio.
--}}
@php
    $anterior = url()->previous();
    $volverA = str_starts_with($anterior, url('/')) ? $anterior : url('/panel');
@endphp
@extends('errors::minimal')

@section('title', 'Tu sesión caducó')
@section('code', '419')
@section('message', 'Pasó mucho tiempo sin actividad y, por seguridad, la sesión se cerró. Lo que enviaste no quedó guardado: vuelve a la página y repítelo.')

@section('acciones')
    <x-boton :href="$volverA">Volver a la página</x-boton>
@endsection
