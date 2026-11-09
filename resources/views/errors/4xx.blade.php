{{--
    Lo que no tiene página propia. Un 422 es una regla de dominio rota por
    la petición (bootstrap/app.php): su mensaje está en español y dice qué
    no se pudo. El resto trae mensajes de Symfony en inglés, que no se
    enseñan.
--}}
@extends('errors::minimal')

@section('title', 'No se pudo hacer')
@section('code', (string) $exception->getStatusCode())
@section('message', $exception->getStatusCode() === 422 && $exception->getMessage() !== '' ? $exception->getMessage() : 'Esta dirección no admite lo que se pidió. Vuelve a la página anterior e inténtalo desde ahí.')
