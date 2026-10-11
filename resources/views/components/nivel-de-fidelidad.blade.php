{{--
    Nivel de fidelidad de un simulador. Sin asignar no es un dato vacío: es
    un simulador que espera a que el ADMIN lo complete (RF39), y la pantalla
    tiene que decirlo con esas palabras.
--}}
@props(['nivel' => null, 'tipo' => null])

@if ($tipo !== null && ! $tipo->admiteNivelFidelidad())
    <span class="text-[14px] text-gray-400" title="El nivel de fidelidad solo aplica a los simuladores">—</span>
@elseif ($nivel === null)
    <span {{ $attributes->class('inline-flex h-6 items-center whitespace-nowrap rounded-full bg-amber-50 px-2.5 text-[12px] font-semibold text-amber-900') }}>
        Pendiente de asignar
    </span>
@else
    <span {{ $attributes->class('inline-flex h-6 items-center whitespace-nowrap rounded-full bg-gray-100 px-2.5 text-[12px] font-semibold text-gray-800') }}>
        {{ $nivel->etiqueta() }}
    </span>
@endif
