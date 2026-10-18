{{-- Enlace a la política de datos de la universidad (QA-11). Sin URL, no pinta nada. --}}
@php($url = \App\Support\PoliticaDeDatos::url())
@if ($url)
    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" {{ $attributes }}>Política de tratamiento de datos personales</a>
@endif
