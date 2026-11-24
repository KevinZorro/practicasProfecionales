{{--
    Pedir el enlace para elegir una contraseña nueva (ADR 0011). Responde
    lo mismo exista o no la cuenta.

    Después de pedirlo, la pantalla cambia a «Revisa tu correo»: a qué
    dirección salió y dónde buscarlo. Se puede reenviar, con una espera que
    crece con cada envío (EsperaDeReenvio); a pedido del usuario la espera
    no se muestra: si se adelanta, se le dice que espere unos minutos.
--}}
@php($enviadoA = session('enviadoA'))

<x-pantalla-de-acceso titulo="Recupera tu contraseña" bajada="Te enviamos un enlace a tu correo para que elijas una nueva. También sirve para elegir la primera.">
    @if ($enviadoA)
        <div class="flex size-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-700" aria-hidden="true">
            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="5" width="18" height="14" rx="2.5"/>
                <path d="m4 7 8 6 8-6"/>
            </svg>
        </div>

        <h2 class="mt-5 text-[1.5rem] font-bold leading-[1.1] tracking-[-0.025em]" role="status">Revisa tu correo</h2>
        <p class="mt-3 text-[15px] leading-relaxed text-portada-gris">
            Si <strong class="break-all font-semibold text-portada-tinta">{{ $enviadoA }}</strong> tiene cuenta en la plataforma,
            ya te llegó un enlace para elegir una contraseña nueva. Vale {{ config('auth.passwords.users.expire') }} minutos y sirve una sola vez.
        </p>

        <ul class="mt-5 space-y-2 rounded-2xl bg-niebla p-4 text-[14px] leading-snug text-portada-gris">
            <li>Puede tardar uno o dos minutos en llegar.</li>
            <li>Si no lo ves, busca en <strong class="font-semibold text-portada-tinta">spam</strong> o en <strong class="font-semibold text-portada-tinta">promociones</strong>.</li>
            <li>Si pides otro, el enlace anterior deja de servir. Entre un envío y otro hay que esperar unos minutos.</li>
        </ul>

        @if (session('antesDeTiempo'))
            <x-aviso tono="peligro" role="alert" class="mt-5">Acabamos de enviarte un enlace. Espera unos minutos antes de pedir otro y revisa también la carpeta de spam.</x-aviso>
        @endif

        <form method="POST" action="{{ route('acceso.recuperar.enviar') }}" class="mt-6">
            @csrf
            <input type="hidden" name="email" value="{{ $enviadoA }}">

            <button type="submit"
                    class="flex min-h-12 w-full items-center justify-center rounded-full bg-white px-6 text-[16px] font-semibold text-portada-tinta ring-1 ring-inset ring-gray-300 transition-colors duration-150 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-portada-rojo">
                Reenviar el enlace
            </button>
        </form>

        <p class="mt-6 flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-[14px]">
            <a href="{{ route('acceso') }}" class="inline-flex min-h-11 items-center font-semibold text-portada-rojo underline-offset-4 hover:underline">Volver a entrar</a>
            <a href="{{ route('acceso.recuperar') }}" class="inline-flex min-h-11 items-center font-semibold text-portada-gris underline-offset-4 hover:text-portada-tinta hover:underline">Usar otro correo</a>
        </p>
    @else
        <h2 class="text-[1.5rem] font-bold leading-[1.1] tracking-[-0.025em]">¿Olvidaste tu contraseña?</h2>
        <p class="mt-2 text-[15px] leading-snug text-portada-gris">Escribe el correo de tu cuenta en la plataforma y te enviamos un enlace.</p>

        <form method="POST" action="{{ route('acceso.recuperar.enviar') }}" class="mt-6 space-y-5">
            @csrf

            <div>
                <label for="email" class="campo-etiqueta">Correo</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus autocomplete="username" inputmode="email"
                       @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                       class="campo rounded-xl py-3 sm:py-3 sm:text-base">
                @error('email')
                    <p id="email-error" class="campo-error">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="flex min-h-12 w-full items-center justify-center rounded-full bg-portada-rojo px-6 text-[17px] font-semibold text-white transition-colors duration-150 hover:bg-portada-rojo-hondo focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-portada-rojo">Enviar el enlace</button>
        </form>

        <p class="mt-6 text-center text-[14px]">
            <a href="{{ route('acceso') }}" class="inline-flex min-h-11 items-center font-semibold text-portada-rojo underline-offset-4 hover:underline">Volver a entrar</a>
        </p>
    @endif
</x-pantalla-de-acceso>
