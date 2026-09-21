{{--
    Cuántas unidades hay en cada estado funcional (RF66).

    Reemplaza a la etiqueta de estado única: desde que el estado es por
    cantidad, un ítem con seis operativas y dos defectuosas no tiene "un
    estado", y es además como habla el laboratorio.
--}}
@props(['item'])

@php
    $tonos = [
        'operativo' => 'bg-emerald-50 text-emerald-800 ring-emerald-600/20',
        'en_revision' => 'bg-amber-50 text-amber-800 ring-amber-600/20',
        'defectuoso' => 'bg-rose-50 text-rose-800 ring-rose-600/20',
    ];
    $desglose = $item->desgloseDeUnidades();
@endphp

<div {{ $attributes->class('flex flex-wrap items-center gap-1.5') }}>
    @forelse ($desglose as $estado => $cantidad)
        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $tonos[$estado] }}">
            {{ $cantidad }} {{ mb_strtolower(\App\Enums\EstadoItemInventario::from($estado)->etiqueta()) }}
        </span>
    @empty
        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-500/20">
            Sin unidades
        </span>
    @endforelse
</div>
