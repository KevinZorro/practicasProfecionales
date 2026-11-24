{{--
    Entrada a la plataforma: con correo y contraseña (ADR 0011) y con la
    cuenta institucional de Google (RF18), cada una si está disponible. Si
    algo impide entrar, aquí se dice por qué.
--}}
<x-pantalla-de-acceso titulo="Te damos la bienvenida" bajada="Solicitudes de escenario, preparación, inventario y evaluaciones del laboratorio, en un solo lugar.">
    <h2 class="text-[1.5rem] font-bold leading-[1.1] tracking-[-0.025em]">Entrar</h2>
    <p class="mt-2 text-[15px] leading-snug text-portada-gris">Solo entran quienes ya tienen cuenta en la plataforma.</p>

    @if (session('estado'))
        <x-aviso tono="exito" role="status" class="mt-5">{{ session('estado') }}</x-aviso>
    @endif

    @error('acceso')
        <x-aviso tono="peligro" role="alert" class="mt-5">
            {{ $message }}
            @if ($conContrasena)
                <a href="{{ route('acceso.recuperar') }}" class="font-semibold underline underline-offset-4">Recupera tu contraseña</a>
            @endif
        </x-aviso>
    @enderror

    @if ($conContrasena)
        <form method="POST" action="{{ route('acceso.entrar') }}" class="mt-6 space-y-5">
            @csrf

            <div>
                <label for="email" class="campo-etiqueta">Correo</label>
                {{-- Con el correo ya escrito (volvió con un error), el foco va a la contraseña. --}}
                <input type="email" name="email" id="email" value="{{ old('email') }}" required @if (! old('email')) autofocus @endif autocomplete="username" inputmode="email"
                       @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                       class="campo rounded-xl py-3 sm:py-3 sm:text-base">
                @error('email')
                    <p id="email-error" class="campo-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <div class="flex items-baseline justify-between gap-3">
                    <label for="contrasena" class="campo-etiqueta">Contraseña</label>
                    <a href="{{ route('acceso.recuperar') }}" class="-my-2 mb-0 inline-flex min-h-11 items-center px-1 text-[14px] font-semibold text-portada-rojo underline-offset-4 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-portada-rojo">¿Olvidaste tu contraseña?</a>
                </div>
                <x-campo-de-contrasena id="contrasena" autocomplete="current-password" required
                    :autofocus="(bool) old('email')"
                    :describedby="$errors->has('contrasena') ? 'contrasena-error' : null"
                    :aria-invalid="$errors->has('contrasena') ? 'true' : null" />
                @error('contrasena')
                    <p id="contrasena-error" class="campo-error">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="flex min-h-12 w-full items-center justify-center rounded-full bg-portada-rojo px-6 text-[17px] font-semibold text-white transition-colors duration-150 hover:bg-portada-rojo-hondo focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-portada-rojo">Entrar</button>
        </form>
    @endif

    @if ($conGoogle)
        @if ($conContrasena)
            <div class="my-6 flex items-center gap-4 text-[13px] text-portada-gris" aria-hidden="true">
                <span class="h-px flex-1 bg-portada-linea"></span>o<span class="h-px flex-1 bg-portada-linea"></span>
            </div>
        @else
            <div class="mt-6"></div>
        @endif

        {{-- El botón de Google con su «G»: es como se reconoce esta entrada (sus
             pautas de marca lo piden así), dibujada aquí para no pedir nada a
             otro sitio (Content-Security-Policy). --}}
        <a href="{{ route('acceso.google') }}"
           class="flex min-h-12 w-full items-center justify-center gap-3 rounded-full bg-white px-5 text-[15px] font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 transition-colors duration-150 hover:bg-gray-50 hover:ring-gray-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rojo">
            <svg class="size-5" viewBox="0 0 48 48" aria-hidden="true">
                <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/>
                <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/>
                <path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/>
                <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/>
            </svg>
            Entrar con Google
        </a>

        <p class="mt-3 text-[13px] leading-snug text-portada-gris">
            Con tu cuenta institucional de Google{{ $dominio ? ", la que termina en @{$dominio}" : '' }}.
        </p>
    @endif
</x-pantalla-de-acceso>
