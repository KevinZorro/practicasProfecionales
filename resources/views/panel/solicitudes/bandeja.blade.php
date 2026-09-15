@extends('layouts.panel')

@section('titulo', 'Solicitudes de escenario')

@section('contenido')
    {{--
        PENDIENTE (§11.1 de CLAUDE.md): está sin decidir con el cliente si un
        usuario con rol docente y coordinador puede aprobar su propia
        solicitud. Hoy sí puede. Se avisa aquí para que quien revise lo sepa
        mientras se resuelve; la regla, cuando llegue, entra en la Policy.
    --}}
    <p class="mb-4 rounded-md bg-sky-50 px-4 py-3 text-sm text-sky-900 ring-1 ring-inset ring-sky-600/20">
        Un usuario que sea coordinador y además docente todavía puede aprobar sus propias solicitudes.
        Está pendiente de definir con la coordinación del laboratorio si debe impedirse.
    </p>

    <livewire:solicitud.bandeja-revision />
@endsection
