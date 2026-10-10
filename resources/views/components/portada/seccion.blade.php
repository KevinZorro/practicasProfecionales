@props([
    'id',
    'titulo',
    'subtitulo' => null,
    // Las secciones alternan blanco y gris muy claro.
    'fondo' => 'blanco',
    // Cómo abre la sección. "apilada": el titular y, debajo, el contenido a
    // lo ancho. "lateral": en escritorio, la cabecera a la izquierda y el
    // contenido a la derecha, mitad y mitad (a 72 px, una línea como
    // «Formación con» ya pide casi 500 px). "centrada": titular y contenido
    // centrados. Cuál usa cada sección lo decide el ritmo de la página
    // (portada/inicio.blade.php), nunca la cantidad de piezas.
    'disposicion' => 'apilada',
])

<section id="{{ $id }}" aria-labelledby="{{ $id }}-titulo"
         {{ $attributes->class([
             'scroll-mt-4 py-24 md:py-32 lg:py-36',
             'fondo-blanco' => $fondo === 'blanco',
             'fondo-niebla' => $fondo === 'niebla',
         ]) }}>
    <div @class([
            'mx-auto max-w-[1180px] px-4 sm:px-6 lg:px-8',
            'lg:grid lg:grid-cols-12 lg:items-start lg:gap-x-12' => $disposicion === 'lateral',
         ])>
        <header @class([
                    'revelar mb-12 md:mb-16',
                    'lg:col-span-6 lg:mb-0' => $disposicion === 'lateral',
                    'text-center' => $disposicion === 'centrada',
                ])>
            <h2 id="{{ $id }}-titulo"
                @class([
                    'max-w-[18ch] text-balance text-[clamp(2.25rem,1.35rem+3.6vw,4.5rem)] font-extrabold leading-[1.04] tracking-[-0.032em]',
                    'mx-auto' => $disposicion === 'centrada',
                ])>
                {{ $titulo }}
            </h2>
            @if ($subtitulo)
                <p @class([
                       'mt-5 max-w-[36rem] text-pretty text-lg leading-relaxed text-portada-gris sm:text-xl',
                       'mx-auto' => $disposicion === 'centrada',
                   ])>{{ $subtitulo }}</p>
            @endif
        </header>

        @if ($disposicion === 'lateral')
            <div class="lg:col-span-6">{{ $slot }}</div>
        @else
            {{ $slot }}
        @endif
    </div>
</section>
