{{--
    Cuántos elementos van alistados. Se lee de un vistazo desde el celular,
    sin abrir el escenario.
--}}
@props(['alistados', 'total'])

@php($porcentaje = $total > 0 ? (int) round($alistados / $total * 100) : 0)

<div {{ $attributes->merge() }}>
    <div class="flex items-center justify-between text-xs font-medium text-gray-600">
        <span>Material alistado</span>
        <span class="tabular-nums">{{ $alistados }} de {{ $total }}</span>
    </div>
    <div class="mt-1 h-2 overflow-hidden rounded-full bg-gray-200"
         role="progressbar" aria-valuenow="{{ $alistados }}" aria-valuemin="0" aria-valuemax="{{ $total }}">
        <div class="h-full rounded-full {{ $porcentaje === 100 ? 'bg-emerald-600' : 'bg-sky-600' }}"
             style="width: {{ $porcentaje }}%"></div>
    </div>
</div>
