{{-- Las iniciales de una persona en un círculo. Sin foto ni servicio
     externo: dos letras, saltando los tratamientos como «Dr.». --}}
@props(['nombre'])

@php
    $iniciales = collect(preg_split('/\s+/', trim($nombre)) ?: [])
        ->reject(static fn (string $parte): bool => $parte === '' || str_ends_with($parte, '.'))
        ->take(2)
        ->map(static fn (string $parte): string => mb_strtoupper(mb_substr($parte, 0, 1)))
        ->implode('');
@endphp

<span {{ $attributes->class('flex shrink-0 items-center justify-center rounded-full font-semibold') }} aria-hidden="true">{{ $iniciales }}</span>
