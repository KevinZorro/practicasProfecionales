@props([
    // Rutas en el disco público, ya comprobadas por PortadaService. Una pieza
    // trae foto, video o las dos: con las dos, la foto es la portada del video.
    'imagen' => null,
    'video' => null,
    'alt' => '',
    // Nombre del video para quien no lo ve; la imagen usa `alt`.
    'etiqueta' => '',
    'prioritaria' => false,
    // Dentro de un enlace no se puede reproducir: ahí el video se ve como su
    // primer cuadro y se reproduce en la página a la que lleva el enlace.
    'controles' => true,
])

@if ($video)
    @php
        $disco = \Illuminate\Support\Facades\Storage::disk(\App\Services\ImagenPublicaService::DISCO);
    @endphp
    {{-- Sin autoplay y sin descargar el video entero: preload="metadata" trae
         solo lo necesario para mostrar el primer cuadro (#t=0.1), así que una
         conexión lenta o un equipo de gama baja no cargan lo que nadie pidió. --}}
    <video @if ($controles) controls @endif
           playsinline
           preload="metadata"
           @if ($imagen) poster="{{ $disco->url($imagen) }}" @endif
           @if ($controles) aria-label="{{ $etiqueta !== '' ? $etiqueta : 'Video' }}" @else aria-hidden="true" tabindex="-1" @endif
           {{ $attributes->class('size-full object-cover') }}>
        <source src="{{ $disco->url($video) }}#t=0.1" type="{{ app(\App\Services\VideoPublicoService::class)->tipoDe($video) }}">
    </video>
@elseif ($imagen)
    <x-portada.foto :ruta="$imagen" :alt="$alt" :prioritaria="$prioritaria" {{ $attributes }} />
@endif
