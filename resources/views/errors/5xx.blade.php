@extends('errors::minimal')

@section('title', 'Algo falló de nuestro lado')
@section('code', (string) $exception->getStatusCode())
@section('message', 'No es por algo que hayas hecho. Vuelve a intentarlo en unos minutos.')
