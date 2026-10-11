{{--
    Cuántos elementos van alistados. Se lee de un vistazo desde el celular,
    sin abrir el escenario: tinta mientras falta, verde cuando está completo.
--}}
@props(['alistados', 'total'])

@php($porcentaje = $total > 0 ? (int) round($alistados / $total * 100) : 0)

<div {{ $attributes->merge() }}>
    <div class="flex items-center justify-between text-[13px] text-gray-600">
        <span>Material alistado</span>
        <span class="font-semibold tabular-nums text-gray-900">{{ $alistados }} de {{ $total }}</span>
    </div>
    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-200"
         role="progressbar" aria-label="Material alistado" aria-valuenow="{{ $alistados }}" aria-valuemin="0" aria-valuemax="{{ $total }}">
        <div class="h-full rounded-full {{ $porcentaje === 100 ? 'bg-emerald-500' : 'bg-gray-900' }}"
             style="width: {{ $porcentaje }}%"></div>
    </div>
</div>
