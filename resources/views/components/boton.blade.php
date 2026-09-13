@props([
    'variante' => 'primario',
    'tipo' => 'submit',
    'href' => null,
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50';

    $variantes = [
        'primario' => 'bg-sky-700 text-white hover:bg-sky-800 focus-visible:ring-sky-700',
        'secundario' => 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 focus-visible:ring-gray-400',
    ];

    $clases = $base.' '.($variantes[$variante] ?? $variantes['primario']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($clases) }}>{{ $slot }}</a>
@else
    <button type="{{ $tipo }}" {{ $attributes->class($clases) }}>{{ $slot }}</button>
@endif
