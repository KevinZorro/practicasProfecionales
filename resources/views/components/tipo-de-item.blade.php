{{--
    Simulador, equipo clínico o equipo básico. Se distingue por icono y por
    texto además del color: el color solo no basta para quien no lo percibe.
--}}
@props(['tipo'])

@php
    $tono = match ($tipo) {
        \App\Enums\TipoItemInventario::Simulador => 'bg-indigo-50 text-indigo-800 ring-indigo-600/20',
        \App\Enums\TipoItemInventario::EquipoClinico => 'bg-teal-50 text-teal-800 ring-teal-600/20',
        \App\Enums\TipoItemInventario::EquipoBasico => 'bg-stone-100 text-stone-800 ring-stone-600/20',
    };

    $icono = match ($tipo) {
        // Persona: el maniquí.
        \App\Enums\TipoItemInventario::Simulador => 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.25a7.5 7.5 0 0115 0',
        // Latido: el equipo clínico.
        \App\Enums\TipoItemInventario::EquipoClinico => 'M3 12h4l2-7 4 14 2-7h6',
        // Caja: el equipo básico.
        \App\Enums\TipoItemInventario::EquipoBasico => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
    };
@endphp

<span {{ $attributes->class("inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {$tono}") }}>
    <svg class="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icono }}"/>
    </svg>
    {{ $tipo->etiqueta() }}
</span>
