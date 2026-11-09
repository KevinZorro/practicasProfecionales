{{--
    «Escribe al laboratorio a … o llama al …», con los enlaces que abren el
    correo y el marcador. Sin contacto cargado no pinta nada: mejor nada que
    un «comunícate» sin decir cómo.

    Uso: <x-contacto-del-laboratorio class="..." /> o, con lo que va antes,
    <x-contacto-del-laboratorio>¿Dudas?</x-contacto-del-laboratorio>
--}}
@php($contacto = app(\App\Services\ConfiguracionLandingService::class)->contacto())

@if ($contacto->hayContacto())
    <span {{ $attributes }}>
        @if (! $slot->isEmpty()){{ $slot }} @endif
        @if ($contacto->correo)
            Escribe al laboratorio a <a href="mailto:{{ $contacto->correo }}" class="font-semibold text-gray-900 underline underline-offset-4 hover:decoration-2">{{ $contacto->correo }}</a>@if ($contacto->telefono) o llama al <a href="tel:{{ $contacto->telefonoParaMarcar() }}" class="whitespace-nowrap font-semibold text-gray-900 underline underline-offset-4 hover:decoration-2">{{ $contacto->telefono }}</a>@endif.
        @else
            Llama al laboratorio al <a href="tel:{{ $contacto->telefonoParaMarcar() }}" class="whitespace-nowrap font-semibold text-gray-900 underline underline-offset-4 hover:decoration-2">{{ $contacto->telefono }}</a>.
        @endif
    </span>
@endif
