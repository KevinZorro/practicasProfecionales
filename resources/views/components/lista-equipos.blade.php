{{--
    Equipos de una solicitud, agrupados por tipo (simulador, equipo clínico,
    equipo básico). Se repite en el formulario, en la bandeja y en el detalle
    del calendario.

    "faltantes" es opcional y solo lo pasa la bandeja: id del ítem => unidades
    libres. El docente nunca lo recibe, porque no ve disponibilidad (RF40).
--}}
@props(['items', 'cantidades' => null, 'faltantes' => []])

@php
    $porTipo = collect($items)->groupBy(fn ($item) => $item->tipo->value);
@endphp

<div {{ $attributes->class('space-y-4') }}>
    @forelse ($porTipo as $tipo => $delTipo)
        <div>
            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                {{ \App\Enums\TipoItemInventario::from($tipo)->etiqueta() }}
            </h4>
            <ul class="space-y-1">
                @foreach ($delTipo as $item)
                    @php($cantidad = $cantidades[$item->id] ?? $item->pivot?->cantidad ?? 1)
                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-md bg-gray-50 px-3 py-2 text-sm">
                        <span class="min-w-0 flex-1 truncate text-gray-900">{{ $item->nombre }}</span>
                        <span class="shrink-0 font-medium text-gray-700">×{{ $cantidad }}</span>
                        @if (array_key_exists($item->id, $faltantes))
                            <span class="w-full rounded bg-amber-50 px-2 py-1 text-xs text-amber-900 ring-1 ring-inset ring-amber-600/20 sm:w-auto">
                                Solo quedan {{ $faltantes[$item->id] }} libres en esa franja
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @empty
        <p class="text-sm text-gray-500">Sin equipos asociados.</p>
    @endforelse
</div>
