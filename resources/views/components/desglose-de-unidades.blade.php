{{--
    Cuántas unidades hay en cada estado funcional (RF66).

    Reemplaza a la etiqueta de estado única: desde que el estado es por
    cantidad, un ítem con seis operativas y dos defectuosas no tiene "un
    estado", y es además como habla el laboratorio.
--}}
@props(['item'])

@php
    $tonos = [
        'operativo' => 'bg-emerald-50 text-emerald-800',
        'en_revision' => 'bg-amber-50 text-amber-900',
        'defectuoso' => 'bg-rojo-50 text-rojo-800',
    ];
    $desglose = $item->desgloseDeUnidades();
@endphp

<div {{ $attributes->class('flex flex-wrap items-center gap-1.5') }}>
    @forelse ($desglose as $estado => $cantidad)
        <span class="inline-flex h-6 items-center whitespace-nowrap rounded-full px-2.5 text-[12px] font-semibold {{ $tonos[$estado] }}">
            {{ $cantidad }} {{ mb_strtolower(\App\Enums\EstadoItemInventario::from($estado)->etiqueta()) }}
        </span>
    @empty
        <span class="inline-flex h-6 items-center whitespace-nowrap rounded-full bg-gray-100 px-2.5 text-[12px] font-semibold text-gray-700">
            Sin unidades
        </span>
    @endforelse
</div>
