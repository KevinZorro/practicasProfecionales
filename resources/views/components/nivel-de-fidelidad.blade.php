{{--
    Nivel de fidelidad de un simulador. Sin asignar no es un dato vacío: es
    un simulador que espera a que el ADMIN lo complete (RF39), y la pantalla
    tiene que decirlo con esas palabras.
--}}
@props(['nivel' => null, 'tipo' => null])

@if ($tipo !== null && ! $tipo->admiteNivelFidelidad())
    <span class="text-sm text-gray-400" title="El nivel de fidelidad solo aplica a los simuladores">—</span>
@elseif ($nivel === null)
    <span {{ $attributes->class('inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-900 ring-1 ring-inset ring-amber-600/20') }}>
        Pendiente de asignar
    </span>
@else
    <span {{ $attributes->class('inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800 ring-1 ring-inset ring-gray-500/20') }}>
        {{ $nivel->etiqueta() }}
    </span>
@endif
