{{-- La firma de los correos: quién escribe y cómo responderle, si el laboratorio cargó su contacto. --}}
@php($contacto = app(\App\Services\ConfiguracionLandingService::class)->contacto())
<p style="margin-top: 24px;">
    {{ config('app.name') }}
    @if ($contacto->correo)
        <br><a href="mailto:{{ $contacto->correo }}" style="color: #1d1d1f;">{{ $contacto->correo }}</a>
    @endif
    @if ($contacto->telefono)
        <br>{{ $contacto->telefono }}
    @endif
</p>
