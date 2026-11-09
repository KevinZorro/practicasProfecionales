{{--
    Cuántos elementos van alistados. Se lee de un vistazo desde el celular,
    sin abrir el escenario: tinta mientras falta, verde y «Todo alistado»
    cuando está completo.

    La barra avanza al marcar en el tablero (DESIGN.md, «Progreso del
    montaje»): se escala en vez de cambiar de ancho, para no recalcular la
    página en un equipo de gama baja. Con movimiento reducido salta de una
    vez y solo cambia el color.
--}}
@props(['alistados', 'total'])

@php
    $completo = $total > 0 && $alistados === $total;
    $proporcion = $total > 0 ? round($alistados / $total, 4) : 0;
@endphp

<div {{ $attributes->merge() }}>
    <div class="flex items-center justify-between gap-3 text-[13px] text-gray-600">
        <span class="flex flex-wrap items-center gap-x-2">
            Material alistado
            @if ($completo)
                <span class="inline-flex items-center gap-1 font-semibold text-emerald-700">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.6" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                    Todo alistado
                </span>
            @endif
        </span>
        <span class="font-semibold text-gray-900">{{ $alistados }} de {{ $total }}</span>
    </div>
    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-200"
         role="progressbar" aria-label="Material alistado" aria-valuenow="{{ $alistados }}" aria-valuemin="0" aria-valuemax="{{ $total }}">
        <div @class([
                 'h-full w-full origin-left rounded-full transition-[transform,background-color] duration-[240ms] ease-llegada motion-reduce:transition-colors motion-reduce:duration-150',
                 'bg-emerald-500' => $completo,
                 'bg-gray-900' => ! $completo,
             ])
             style="transform: scaleX({{ $proporcion }})"></div>
    </div>
</div>
