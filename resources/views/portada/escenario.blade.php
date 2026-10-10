{{--
    Detalle ampliado de un escenario clínico (RF03): qué recrea, con qué
    equipo, y el camino para que un docente lo solicite. Solo existe para
    los escenarios publicados; los demás dan 404 (PortadaService).
--}}
@extends('layouts.publico', [
    'enlaces' => [
        route('inicio.publico').'#escenarios' => 'Escenarios',
        route('inicio.publico').'#contacto' => 'Contacto',
    ],
])

@section('titulo', $escenario->nombre.' · '.config('app.name'))
@section('descripcion', \Illuminate\Support\Str::limit($escenario->descripcion, 155))

@section('contenido')

    <article aria-labelledby="escenario-titulo">
        <header class="bg-white pb-12 pt-12 sm:pt-16 lg:pb-16">
            <div class="mx-auto max-w-[1180px] px-4 sm:px-6 lg:px-8">
                <a href="{{ route('inicio.publico') }}#escenarios"
                   class="inline-flex items-center gap-1.5 text-[15px] font-semibold text-portada-rojo underline-offset-4 hover:underline focus-visible:rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-portada-rojo">
                    <x-portada.chevron direccion="izquierda" /> Todos los escenarios
                </a>

                <h1 id="escenario-titulo"
                    class="mt-8 max-w-[16ch] text-balance text-[clamp(2.75rem,1.4rem+5.4vw,5.5rem)] font-extrabold leading-[1.02] tracking-[-0.038em]">
                    {{ $escenario->nombre }}
                </h1>
                <p class="mt-6 max-w-[44rem] text-pretty text-xl leading-relaxed text-portada-gris sm:text-2xl sm:leading-relaxed">
                    {{ $escenario->descripcion }}
                </p>
            </div>
        </header>

        @if ($escenario->imagen)
            <div class="mx-auto max-w-[1400px] px-4 sm:px-6 lg:px-8">
                <div class="aspect-[4/3] overflow-hidden rounded-[28px] bg-portada-niebla sm:aspect-[16/9]">
                    <x-portada.foto :ruta="$escenario->imagen" :alt="$escenario->nombre" :prioritaria="true" />
                </div>
            </div>
        @endif

        <section aria-label="Capacidades y equipo" class="fondo-blanco py-20 md:py-28">
            <div class="mx-auto grid max-w-[1180px] gap-14 px-4 sm:px-6 lg:grid-cols-2 lg:gap-20 lg:px-8">
                @if ($escenario->capacidades->isNotEmpty())
                    <div class="revelar">
                        <h2 class="text-3xl font-extrabold tracking-[-0.03em] sm:text-4xl">Qué recrea.</h2>
                        <ul class="mt-8 flex flex-wrap gap-2.5">
                            @foreach ($escenario->capacidades as $capacidad)
                                <li class="rounded-full bg-portada-niebla px-4 py-2 text-[17px] font-medium">{{ $capacidad->nombre }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($escenario->items->isNotEmpty())
                    <div class="revelar" style="--orden: 1">
                        <h2 class="text-3xl font-extrabold tracking-[-0.03em] sm:text-4xl">Con qué equipo.</h2>
                        <x-portada.caracteristicas :lista="$escenario->items->pluck('nombre')->all()" class="mt-8" />
                    </div>
                @endif
            </div>
        </section>

        <section aria-labelledby="solicitar-titulo" class="fondo-niebla py-20 md:py-28">
            <div class="revelar mx-auto max-w-[1180px] px-4 text-center sm:px-6 lg:px-8">
                <h2 id="solicitar-titulo" class="mx-auto max-w-[20ch] text-balance text-[clamp(2rem,1.4rem+2.4vw,3.25rem)] font-extrabold leading-[1.06] tracking-[-0.03em]">
                    ¿Eres docente? Solicítalo para tu clase.
                </h2>
                <p class="mx-auto mt-4 max-w-[36rem] text-pretty text-lg text-portada-gris">
                    Entra con tu cuenta institucional y elige este escenario al crear la solicitud.
                </p>
                <a href="{{ route('panel.solicitudes.nueva') }}"
                   class="mt-9 inline-flex rounded-full bg-portada-rojo px-7 py-3.5 text-[17px] font-semibold text-white transition-colors duration-150 hover:bg-portada-rojo-hondo focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-portada-rojo">
                    Solicitar el escenario
                </a>
            </div>
        </section>
    </article>

    @if ($otros->isNotEmpty())
        <section aria-labelledby="otros-titulo" class="fondo-blanco py-20 md:py-28">
            <div class="mx-auto max-w-[1180px] px-4 sm:px-6 lg:px-8">
                <h2 id="otros-titulo" class="revelar mb-10 text-3xl font-extrabold tracking-[-0.03em] sm:text-4xl">Otros escenarios.</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($otros as $otro)
                        <x-portada.escenario :escenario="$otro" :orden="$loop->index" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

@endsection
