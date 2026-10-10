@props(['docente', 'orden' => 0])

@php
    $iniciales = collect(preg_split('/\s+/', trim($docente->nombre)) ?: [])
        ->filter()
        ->take(2)
        ->map(static fn (string $parte): string => mb_strtoupper(mb_substr($parte, 0, 1)))
        ->implode('');
@endphp

{{--
    La credencial de un docente (RF07). Foto, texto y títulos son hijos
    directos de la rejilla: con cursor, los títulos se superponen al pie de
    la foto y aparecen mientras la foto se eleva, al pasar por encima o al
    llegar con el teclado. En pantallas táctiles van debajo del cargo y se
    ven siempre (animación 6 de DESIGN.md, en app.css).
--}}
<article class="portada-credencial revelar rounded-[28px] bg-[var(--pieza)] p-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-portada-rojo"
         tabindex="0" style="--orden: {{ min($orden, 3) }}">
    <div class="portada-credencial-foto aspect-[4/5] overflow-hidden rounded-[20px] bg-[var(--pieza-inversa)]">
        @if ($docente->foto)
            <x-portada.foto :ruta="$docente->foto" :alt="$docente->nombre" class="rounded-[20px]" />
        @else
            <div class="flex size-full items-center justify-center" aria-hidden="true">
                <span class="text-[5rem] font-extralight tracking-[-0.04em] text-portada-tinta/30">{{ $iniciales }}</span>
            </div>
        @endif
    </div>

    <div class="px-5 pb-6 pt-6">
        <h3 class="text-xl font-bold leading-tight tracking-[-0.02em]">{{ $docente->nombre }}</h3>
        <p class="mt-1 text-[15px] text-portada-gris">{{ $docente->cargo }}</p>
    </div>

    @if ($docente->titulos->isNotEmpty())
        <ul class="portada-credencial-titulos mx-5 mb-6 space-y-3 border-t border-portada-linea pt-5" aria-label="Títulos académicos">
            @foreach ($docente->titulos as $titulo)
                <li class="text-[15px] leading-snug">
                    <span class="block font-medium">{{ $titulo->titulo }}</span>
                    <span class="block text-sm text-portada-gris">{{ $titulo->institucion }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</article>
