@props([
    'taller',
    'destacado' => false,
    'correo' => null,
    'orden' => 0,
])

{{--
    Un taller o curso (RF04): imagen, tema, fecha y modalidad. Mientras no
    exista el formulario por taller (RF09), "Pedir información" abre un
    correo al laboratorio con el taller en el asunto.
--}}
<article style="--orden: {{ min($orden, 3) }}"
         @class([
             'revelar flex grow flex-col overflow-hidden rounded-[28px] bg-[var(--pieza)]',
             'basis-full lg:basis-[calc(50%-0.5rem)]' => $destacado,
             'basis-full sm:basis-[calc(50%-0.5rem)] lg:basis-[calc(25%-0.75rem)]' => ! $destacado,
         ])>
    @if ($taller->imagen)
        <div @class([
                'm-2 mb-0 overflow-hidden rounded-[20px] bg-[var(--pieza-inversa)]',
                'aspect-[16/10]' => ! $destacado,
                'aspect-[16/10] lg:aspect-[2/1]' => $destacado,
             ])>
            <x-portada.foto :ruta="$taller->imagen" :alt="''" />
        </div>
    @endif

    <div @class(['flex flex-1 flex-col p-7', 'sm:p-10' => $destacado])>
        <h3 @class([
                'text-balance font-bold leading-[1.1] tracking-[-0.025em]',
                'text-[clamp(1.875rem,1.4rem+1.8vw,3rem)]' => $destacado,
                'text-2xl' => ! $destacado,
            ])>
            {{ $taller->titulo }}
        </h3>
        @if ($destacado)
            <p class="mt-4 max-w-[46ch] text-pretty text-lg leading-relaxed text-portada-gris">{{ $taller->descripcion }}</p>
        @endif

        <dl class="mt-6 flex flex-wrap gap-x-5 gap-y-2 text-[15px]">
            <div class="flex gap-1.5">
                <dt class="sr-only">Tema</dt>
                <dd class="font-semibold text-portada-rojo">{{ $taller->tema }}</dd>
            </div>
            <div class="flex gap-1.5">
                <dt class="sr-only">Fecha</dt>
                <dd class="font-semibold"><time datetime="{{ $taller->fecha->toDateString() }}">{{ $taller->fecha->translatedFormat('j \d\e F \d\e Y') }}</time></dd>
            </div>
            <div class="flex gap-1.5">
                <dt class="sr-only">Modalidad</dt>
                <dd class="text-portada-gris">{{ $taller->modalidad->etiqueta() }}</dd>
            </div>
        </dl>

        @if ($correo)
            <a href="mailto:{{ $correo }}?subject={{ rawurlencode('Información: '.$taller->titulo) }}"
               class="mt-auto inline-flex items-center gap-1.5 self-start pt-8 text-[15px] font-semibold text-portada-rojo underline-offset-4 hover:underline focus-visible:rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-portada-rojo">
                Pedir información <x-portada.chevron />
            </a>
        @endif
    </div>
</article>
