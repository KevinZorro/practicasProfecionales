{{--
    Portada pública del laboratorio (RF01–RF07). Todo lo que se ve lo carga
    el ADMIN en /admin; una sección sin contenido no se pinta.

    Diseño, tokens y las seis animaciones: DESIGN.md. Una sola idea por
    sección y los fondos alternan blanco y gris muy claro.
--}}
@extends('layouts.publico', [
    'enlaces' => array_filter([
        '#equipamiento' => $contenido->equipos->isNotEmpty() ? 'Equipamiento' : null,
        '#escenarios' => $contenido->escenarios->isNotEmpty() ? 'Escenarios' : null,
        '#oferta' => $contenido->hayOfertaAcademica() ? 'Oferta académica' : null,
        '#docentes' => $contenido->docentes->isNotEmpty() ? 'Docentes' : null,
        '#contacto' => $contenido->hayContacto() ? 'Contacto' : null,
    ]),
])

@section('titulo', config('app.name').' · UFPS')

@section('contenido')

    @php
        // Los fondos alternan gris y blanco entre las secciones que de verdad
        // se pintan: si falta una, la siguiente no repite el color.
        $presentes = array_keys(array_filter([
            'cifras' => $contenido->cifras->isNotEmpty(),
            'equipamiento' => $contenido->equipos->isNotEmpty(),
            'escenarios' => $contenido->escenarios->isNotEmpty(),
            'oferta' => $contenido->hayOfertaAcademica(),
            'galeria' => $contenido->fotos->isNotEmpty(),
            'certificaciones' => $contenido->certificaciones->isNotEmpty(),
            'docentes' => $contenido->docentes->isNotEmpty(),
            'contacto' => $contenido->hayContacto(),
        ]));
        $fondos = [];
        foreach ($presentes as $posicion => $seccion) {
            $fondos[$seccion] = $posicion % 2 === 0 ? 'niebla' : 'blanco';
        }
    @endphp

    {{-- Hero: el titular, la línea de pulso y el laboratorio en marcha (RF02). --}}
    <section aria-labelledby="titulo-principal" class="overflow-hidden bg-white pb-16 pt-20 sm:pt-28 lg:pb-24 lg:pt-32">
        <div class="mx-auto max-w-[1180px] px-4 text-center sm:px-6 lg:px-8">
            <h1 id="titulo-principal"
                class="portada-titular mx-auto max-w-[15ch] text-balance text-[clamp(2.75rem,1.2rem+6.4vw,6rem)] font-extrabold leading-[1.02] tracking-[-0.038em]">
                {{ $contenido->titulo }}
            </h1>

            @if ($contenido->subtitulo)
                <p class="mx-auto mt-6 max-w-[40rem] text-pretty text-lg leading-relaxed text-portada-gris sm:text-[1.375rem] sm:leading-relaxed">
                    {{ $contenido->subtitulo }}
                </p>
            @endif

            @if ($contenido->escenarios->isNotEmpty() || $contenido->hayOfertaAcademica())
                <div class="mt-10 flex flex-wrap items-center justify-center gap-x-8 gap-y-4">
                    @if ($contenido->escenarios->isNotEmpty())
                        <a href="#escenarios"
                           class="rounded-full bg-portada-rojo px-7 py-3.5 text-[17px] font-semibold text-white transition-colors duration-150 hover:bg-portada-rojo-hondo focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-portada-rojo">
                            Conoce los escenarios
                        </a>
                    @endif
                    @if ($contenido->hayOfertaAcademica())
                        <a href="#oferta"
                           class="inline-flex items-center gap-1.5 text-[17px] font-semibold text-portada-rojo underline-offset-4 hover:underline focus-visible:rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-portada-rojo">
                            Ver la oferta académica <x-portada.chevron />
                        </a>
                    @endif
                </div>
            @endif
        </div>

        <x-portada.pulso :animado="true" class="mt-12 sm:mt-16" />

        @if ($contenido->video)
            <div class="mx-auto mt-6 max-w-[1400px] px-4 sm:px-6 lg:px-8">
                <div class="relative overflow-hidden rounded-[28px] bg-portada-niebla" data-video-hero>
                    {{-- En silencio y en bucle, como fondo (RF02). Lo arranca
                         portada.js, que respeta prefers-reduced-motion y el
                         ahorro de datos y muestra el botón de pausa. Sin
                         JavaScript se ve el primer cuadro, sin descargar el
                         video entero. --}}
                    <video class="aspect-[4/3] w-full object-cover sm:aspect-[16/9]"
                           muted loop playsinline preload="metadata"
                           aria-label="Video institucional del laboratorio">
                        {{-- #t=0.1: detenido, el navegador pinta ese cuadro en vez de un marco vacío. --}}
                        <source src="{{ \Illuminate\Support\Facades\Storage::disk(\App\Services\ImagenPublicaService::DISCO)->url($contenido->video) }}#t=0.1"
                                type="{{ $contenido->tipoDeVideo }}">
                    </video>
                    <button type="button" hidden data-video-control
                            class="absolute bottom-4 right-4 flex size-11 items-center justify-center rounded-full bg-white text-portada-tinta ring-1 ring-black/[0.06] transition-colors duration-150 hover:bg-portada-niebla focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-portada-rojo sm:bottom-6 sm:right-6">
                        <span class="sr-only" data-video-etiqueta>Pausar el video</span>
                        <svg class="size-4" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" data-video-icono="pausa">
                            <rect x="3.5" y="2.5" width="3" height="11" rx="1"/><rect x="9.5" y="2.5" width="3" height="11" rx="1"/>
                        </svg>
                        <svg class="hidden size-4" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" data-video-icono="reproducir">
                            <path d="M4.5 2.8v10.4a.8.8 0 0 0 1.2.7l8.3-5.2a.8.8 0 0 0 0-1.4L5.7 2.1a.8.8 0 0 0-1.2.7Z"/>
                        </svg>
                    </button>
                </div>
            </div>
        @endif
    </section>

    {{-- Cifras destacadas (RF01): números de monitor que cuentan al llegar. --}}
    @if ($contenido->cifras->isNotEmpty())
        <x-portada.seccion id="cifras" :fondo="$fondos['cifras']" titulo="En cifras.">
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($contenido->cifras as $cifra)
                    <x-portada.cifra :cifra="$cifra" :indice="$loop->index" :total="$loop->count" />
                @endforeach
            </dl>
        </x-portada.seccion>
    @endif

    {{-- Equipamiento destacado (RF01, RF10): los protagonistas. --}}
    @if ($contenido->equipos->isNotEmpty())
        <x-portada.seccion id="equipamiento" :fondo="$fondos['equipamiento']" titulo="Practicar sin riesgo."
                           subtitulo="Cada equipo está aquí para que el error ocurra en el laboratorio y no frente a un paciente.">
            <x-portada.equipo-principal :equipo="$contenido->equipos->first()" />

            @if ($contenido->equipos->count() > 1)
                <div class="mt-16 grid gap-4 md:mt-24 md:grid-cols-2">
                    @foreach ($contenido->equipos->skip(1) as $equipo)
                        <x-portada.equipo :equipo="$equipo" :orden="$loop->index"
                                          :class="$loop->last && $loop->count % 2 === 1 ? 'md:col-span-2' : ''" />
                    @endforeach
                </div>
            @endif
        </x-portada.seccion>
    @endif

    {{-- Escenarios clínicos (RF03), con el primero destacado. --}}
    @if ($contenido->escenarios->isNotEmpty())
        <x-portada.seccion id="escenarios" :fondo="$fondos['escenarios']" titulo="Escenarios que se viven."
                           subtitulo="Cada escenario recrea una situación clínica con sus signos, sus sonidos y su equipo.">
            @php
                // La primera pieza se destaca: a lo ancho, y con foto también
                // a lo alto (2 × 2 en 3 columnas). La última se estira para
                // cerrar su fila, en tablet (2 columnas) y en escritorio (3).
                $total = $contenido->escenarios->count();
                $conDestacado = $total > 2;
                $celdasDestacado = ! $conDestacado ? 1 : ($contenido->escenarios->first()->imagen ? 4 : 2);
                $sobranEnEscritorio = ($total - 1 + $celdasDestacado) % 3;
                $sobranEnTablet = ($total - 1 + ($conDestacado ? 2 : 1)) % 2;
            @endphp
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($contenido->escenarios as $escenario)
                    <x-portada.escenario :escenario="$escenario" :destacado="$loop->first && $conDestacado" :orden="$loop->index"
                                         :ancho="$loop->last && $sobranEnEscritorio === 1"
                                         :class="\Illuminate\Support\Arr::toCssClasses([
                                             'sm:col-span-2' => $loop->last && $sobranEnTablet === 1,
                                             'lg:col-span-1' => $loop->last && $sobranEnTablet === 1 && $sobranEnEscritorio === 0,
                                             'lg:col-span-3' => $loop->last && $sobranEnEscritorio === 1,
                                             'lg:col-span-2' => $loop->last && $sobranEnEscritorio === 2,
                                         ])" />
                @endforeach
            </div>
        </x-portada.seccion>
    @endif

    {{-- Oferta académica: talleres y cursos (RF04) y eventos (RF05). --}}
    @if ($contenido->hayOfertaAcademica())
        <x-portada.seccion id="oferta" :fondo="$fondos['oferta']" titulo="Siempre hay algo por aprender."
                           subtitulo="Talleres, cursos y eventos para seguir formándose.">
            @if ($contenido->talleres->isNotEmpty())
                <h3 class="revelar mb-6 text-2xl font-bold tracking-[-0.02em] sm:text-[1.75rem]">Talleres y cursos</h3>
                {{-- Filas que se llenan solas: cada pieza crece hasta cerrar la suya. --}}
                <div class="flex flex-wrap gap-4">
                    @foreach ($contenido->talleres as $taller)
                        <x-portada.taller :taller="$taller" :destacado="$loop->first && $loop->count > 2" :correo="$contenido->correo" :orden="$loop->index" />
                    @endforeach
                </div>
            @endif

            @if ($contenido->eventos->isNotEmpty())
                <h3 @class(['revelar mb-6 text-2xl font-bold tracking-[-0.02em] sm:text-[1.75rem]', 'mt-16 md:mt-20' => $contenido->talleres->isNotEmpty()])>Próximos eventos</h3>
                <div class="flex flex-wrap gap-4">
                    @foreach ($contenido->eventos as $evento)
                        <x-portada.evento :evento="$evento" :orden="$loop->index" />
                    @endforeach
                </div>
            @endif
        </x-portada.seccion>
    @endif

    {{-- Galería de fotografías (RF01). Solo las que existen en el disco. --}}
    @if ($contenido->fotos->isNotEmpty())
        <x-portada.seccion id="galeria" :fondo="$fondos['galeria']" titulo="Por dentro.">
            @php
                // La primera foto ocupa 4 celdas (2 × 2). La última se estira
                // para cerrar su fila en 2 y en 4 columnas.
                $celdas = $contenido->fotos->count() + 3;
                $sobranEnMovil = $celdas % 2;
                $sobranEnEscritorio = $celdas % 4;
            @endphp
            <div class="grid auto-rows-[11rem] grid-cols-2 gap-4 sm:auto-rows-[15rem] lg:grid-cols-4">
                @foreach ($contenido->fotos as $foto)
                    <figure style="--orden: {{ min($loop->index, 3) }}"
                            @class([
                                'revelar overflow-hidden rounded-[20px] bg-[var(--pieza)]',
                                'col-span-2 row-span-2' => $loop->first,
                                'col-span-2' => ! $loop->first && $loop->last && $sobranEnMovil === 1,
                                'lg:col-span-1' => ! $loop->first && $loop->last && $sobranEnMovil === 1 && $sobranEnEscritorio === 0,
                                'lg:col-span-4' => ! $loop->first && $loop->last && $sobranEnEscritorio === 1,
                                'lg:col-span-3' => ! $loop->first && $loop->last && $sobranEnEscritorio === 2,
                                'lg:col-span-2' => ! $loop->first && $loop->last && $sobranEnEscritorio === 3,
                            ])>
                        <x-portada.foto :ruta="$foto->imagen_path" :alt="$foto->titulo" />
                        <figcaption class="sr-only">{{ $foto->titulo }}</figcaption>
                    </figure>
                @endforeach
            </div>
        </x-portada.seccion>
    @endif

    {{-- Certificaciones como insignias (RF06). --}}
    @if ($contenido->certificaciones->isNotEmpty())
        {{-- Dos insignias caben al costado de la cabecera; más, debajo. --}}
        <x-portada.seccion id="certificaciones" :fondo="$fondos['certificaciones']" titulo="Formación con respaldo."
                           :disposicion="$contenido->certificaciones->count() <= 2 ? 'lateral' : 'apilada'">
            <div class="flex flex-wrap justify-center gap-x-6 gap-y-12 sm:gap-x-10 sm:gap-y-14">
                @foreach ($contenido->certificaciones as $certificacion)
                    <x-portada.insignia :certificacion="$certificacion" :orden="$loop->index" />
                @endforeach
            </div>
        </x-portada.seccion>
    @endif

    {{-- Docentes con sus títulos (RF07). --}}
    @if ($contenido->docentes->isNotEmpty())
        {{-- Con uno o dos docentes, la cabecera va al costado: apilados
             ocupaban un cuarto de la fila. Uno solo se alinea al borde
             derecho, frente al titular; tres llenan su propia fila. --}}
        @php($cantidadDocentes = $contenido->docentes->count())
        <x-portada.seccion id="docentes" :fondo="$fondos['docentes']" titulo="Quienes te acompañan."
                           :disposicion="$cantidadDocentes <= 2 ? 'lateral' : 'apilada'">
            <div @class([
                    'grid grid-cols-1 gap-4',
                    'sm:max-w-[22rem] lg:ml-auto' => $cantidadDocentes === 1,
                    'sm:grid-cols-2' => $cantidadDocentes > 1,
                    'lg:grid-cols-3' => $cantidadDocentes === 3,
                    'lg:grid-cols-4' => $cantidadDocentes > 3,
                 ])>
                @foreach ($contenido->docentes as $docente)
                    <x-portada.credencial :docente="$docente" :orden="$loop->index" />
                @endforeach
            </div>
        </x-portada.seccion>
    @endif

    {{-- Contacto (RF11). --}}
    @if ($contenido->hayContacto())
        <section id="contacto" aria-labelledby="contacto-titulo"
                 @class(['py-24 md:py-32 lg:py-36', $fondos['contacto'] === 'niebla' ? 'fondo-niebla' : 'fondo-blanco'])>
            <div class="mx-auto grid max-w-[1180px] gap-12 px-4 sm:px-6 lg:grid-cols-12 lg:px-8">
                <div class="revelar lg:col-span-6">
                    <h2 id="contacto-titulo" class="max-w-[12ch] text-balance text-[clamp(2.25rem,1.35rem+3.6vw,4.5rem)] font-extrabold leading-[1.04] tracking-[-0.032em]">
                        Ven a conocerlo.
                    </h2>
                    <p class="mt-5 max-w-[30rem] text-pretty text-lg leading-relaxed text-portada-gris sm:text-xl">
                        Escríbenos para visitas, talleres o convenios.
                    </p>
                </div>

                <dl class="revelar divide-y divide-portada-linea border-y border-portada-linea lg:col-span-6" style="--orden: 1">
                    @if ($contenido->direccion)
                        <div class="py-6">
                            <dt class="text-[13px] font-semibold uppercase tracking-[0.14em] text-portada-gris">Dónde estamos</dt>
                            <dd class="mt-2 text-pretty text-xl font-semibold leading-snug tracking-[-0.015em] sm:text-2xl">{{ $contenido->direccion }}</dd>
                        </div>
                    @endif
                    @if ($contenido->telefono)
                        <div class="py-6">
                            <dt class="text-[13px] font-semibold uppercase tracking-[0.14em] text-portada-gris">Teléfono</dt>
                            <dd class="mt-2 text-xl font-semibold tracking-[-0.015em] sm:text-2xl">
                                <a href="tel:{{ preg_replace('/[^\d+]/', '', $contenido->telefono) }}" class="tabular-nums underline-offset-4 hover:text-portada-rojo hover:underline focus-visible:rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-portada-rojo">{{ $contenido->telefono }}</a>
                            </dd>
                        </div>
                    @endif
                    @if ($contenido->correo)
                        <div class="py-6">
                            <dt class="text-[13px] font-semibold uppercase tracking-[0.14em] text-portada-gris">Correo</dt>
                            <dd class="mt-2 break-words text-xl font-semibold tracking-[-0.015em] sm:text-2xl">
                                <a href="mailto:{{ $contenido->correo }}" class="underline-offset-4 hover:text-portada-rojo hover:underline focus-visible:rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-portada-rojo">{{ $contenido->correo }}</a>
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>
        </section>
    @endif

@endsection
