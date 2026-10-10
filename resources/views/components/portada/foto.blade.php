@props([
    // Ruta en el disco público, ya comprobada por PortadaService.
    'ruta',
    'alt' => '',
    // Solo la primera imagen visible se pide sin esperar.
    'prioritaria' => false,
])

<img src="{{ \Illuminate\Support\Facades\Storage::disk(\App\Services\ImagenPublicaService::DISCO)->url($ruta) }}"
     alt="{{ $alt }}"
     loading="{{ $prioritaria ? 'eager' : 'lazy' }}"
     decoding="async"
     {{ $attributes->class('size-full object-cover') }}>
