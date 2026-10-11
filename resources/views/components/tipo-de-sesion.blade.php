{{--
    Práctica o evaluación. Se distinguen por la forma, el ícono y la palabra,
    no por el color: la práctica va rellena y la evaluación en contorno, como
    en el calendario. El color del panel queda para los estados.
--}}
@props(['tipo'])

@php
    $esEvaluacion = $tipo === \App\Enums\TipoSesion::Evaluacion;
    $tono = $esEvaluacion
        ? 'bg-white text-gray-900 ring-1 ring-inset ring-gray-300'
        : 'bg-gray-100 text-gray-700';
    $icono = $esEvaluacion
        ? 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'
        : 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10';
@endphp

<span {{ $attributes->class("inline-flex h-6 shrink-0 items-center gap-1 whitespace-nowrap rounded-full px-2.5 text-[12px] font-semibold {$tono}") }}>
    <svg class="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icono }}"/>
    </svg>
    {{ $tipo->etiqueta() }}
</span>
