{{--
    Formulario de información de un taller (RF09). Lo que se envía llega al
    correo de contacto del laboratorio, que le responde al interesado (D8).
    Solo existe con la sección habilitada y para un taller publicado.

    Un formulario de HTML sin Livewire, como el resto de la portada: funciona
    sin JavaScript y en conexiones lentas (RNF10).
--}}
@extends('layouts.publico', [
    'enlaces' => [
        route('inicio.publico').'#oferta' => 'Oferta académica',
        route('inicio.publico').'#contacto' => 'Contacto',
    ],
])

@section('titulo', 'Información: '.$taller->titulo.' · '.config('app.name'))
@section('descripcion', \Illuminate\Support\Str::limit($taller->descripcion, 155))

@php
    $campo = 'mt-2 block w-full rounded-2xl border border-gray-300 bg-white px-4 py-3 text-[16px] text-portada-tinta placeholder:text-portada-gris/70 focus:border-portada-tinta focus:outline-none focus:ring-2 focus:ring-portada-rojo/30';
    $etiqueta = 'block text-[15px] font-semibold text-portada-tinta';
    $error = 'mt-1.5 text-[14px] font-medium text-portada-rojo';
@endphp

@section('contenido')
    <section aria-labelledby="formulario-titulo" class="bg-white pb-24 pt-12 sm:pt-16 lg:pb-32">
        <div class="mx-auto max-w-[1180px] px-4 sm:px-6 lg:px-8">
            <a href="{{ route('inicio.publico') }}#oferta"
               class="inline-flex items-center gap-1.5 text-[15px] font-semibold text-portada-rojo underline-offset-4 hover:underline focus-visible:rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-portada-rojo">
                <x-portada.chevron direccion="izquierda" /> Toda la oferta académica
            </a>

            <div class="mt-8 grid gap-12 lg:grid-cols-2 lg:gap-12">
                <header>
                    <p class="text-[15px] font-semibold text-portada-rojo">{{ $taller->tema }}</p>
                    <h1 id="formulario-titulo"
                        class="mt-3 max-w-[18ch] text-balance text-[clamp(2.25rem,1.4rem+3.4vw,4rem)] font-extrabold leading-[1.04] tracking-[-0.035em]">
                        {{ $taller->titulo }}
                    </h1>
                    <p class="mt-5 text-lg font-semibold">
                        <time datetime="{{ $taller->fecha->toDateString() }}">{{ $taller->fecha->translatedFormat('j \d\e F \d\e Y') }}</time>
                        <span class="font-normal text-portada-gris">· {{ $taller->modalidad->etiqueta() }}</span>
                    </p>
                    <p class="mt-5 max-w-[46ch] text-pretty text-lg leading-relaxed text-portada-gris">{{ $taller->descripcion }}</p>
                </header>

                <div>
                    @if (session('enviada'))
                        <div role="status" class="rounded-[28px] bg-portada-niebla p-8 sm:p-10">
                            <p class="text-2xl font-bold tracking-[-0.02em]">Solicitud enviada.</p>
                            <p class="mt-3 text-lg leading-relaxed text-portada-gris">{{ session('enviada') }}</p>
                        </div>
                    @else
                        <form method="POST" action="{{ route('portada.taller.informacion.enviar', $taller->id) }}"
                              class="space-y-6 rounded-[28px] bg-portada-niebla p-6 sm:p-10" novalidate>
                            @csrf

                            <h2 class="text-2xl font-bold tracking-[-0.02em]">Pedir información</h2>

                            <div>
                                <label for="nombre" class="{{ $etiqueta }}">Nombre</label>
                                <input type="text" id="nombre" name="nombre" value="{{ old('nombre') }}" required autocomplete="name" maxlength="255"
                                       class="{{ $campo }}" @error('nombre') aria-invalid="true" aria-describedby="nombre-error" @enderror>
                                @error('nombre') <p id="nombre-error" class="{{ $error }}">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="email" class="{{ $etiqueta }}">Correo</label>
                                <input type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="email" maxlength="255"
                                       class="{{ $campo }}" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                                @error('email') <p id="email-error" class="{{ $error }}">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="telefono" class="{{ $etiqueta }}">Teléfono <span class="font-normal text-portada-gris">(opcional)</span></label>
                                <input type="tel" id="telefono" name="telefono" value="{{ old('telefono') }}" autocomplete="tel" maxlength="50"
                                       class="{{ $campo }}" @error('telefono') aria-invalid="true" aria-describedby="telefono-error" @enderror>
                                @error('telefono') <p id="telefono-error" class="{{ $error }}">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="mensaje" class="{{ $etiqueta }}">Qué quiere saber <span class="font-normal text-portada-gris">(opcional)</span></label>
                                <textarea id="mensaje" name="mensaje" rows="4" maxlength="2000"
                                          class="{{ $campo }}" @error('mensaje') aria-invalid="true" aria-describedby="mensaje-error" @enderror>{{ old('mensaje') }}</textarea>
                                @error('mensaje') <p id="mensaje-error" class="{{ $error }}">{{ $message }}</p> @enderror
                            </div>

                            {{-- D16: campo trampa. Fuera de la vista y del teclado; una persona no lo llena. --}}
                            <div class="absolute -left-[9999px] size-px overflow-hidden" aria-hidden="true">
                                <label for="{{ \App\Http\Requests\SolicitudDeInformacionRequest::CAMPO_TRAMPA }}">No llene este campo</label>
                                <input type="text" id="{{ \App\Http\Requests\SolicitudDeInformacionRequest::CAMPO_TRAMPA }}"
                                       name="{{ \App\Http\Requests\SolicitudDeInformacionRequest::CAMPO_TRAMPA }}" tabindex="-1" autocomplete="off">
                            </div>

                            <div>
                                <label class="flex items-start gap-3 text-[15px] leading-relaxed text-portada-tinta">
                                    <input type="checkbox" name="acepta_tratamiento" value="1" @checked(old('acepta_tratamiento'))
                                           class="mt-1 size-5 shrink-0 rounded border border-gray-300 text-portada-rojo focus:ring-portada-rojo/30"
                                           @error('acepta_tratamiento') aria-invalid="true" aria-describedby="acepta-error" @enderror>
                                    <span>
                                        Autorizo al laboratorio a usar estos datos solo para responder esta solicitud.
                                        <x-politica-de-datos class="font-semibold text-portada-rojo underline underline-offset-4" />
                                    </span>
                                </label>
                                @error('acepta_tratamiento') <p id="acepta-error" class="{{ $error }}">{{ $message }}</p> @enderror
                            </div>

                            <button type="submit"
                                    class="rounded-full bg-portada-rojo px-6 py-3 text-base font-semibold text-white transition-colors duration-150 hover:bg-portada-rojo-hondo focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-portada-rojo">
                                Enviar
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
