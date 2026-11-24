{{--
    Elegir la contraseña nueva desde el enlace del correo (ADR 0011). Si el
    enlace ya no sirve, lo dice antes de pedir nada.
--}}
<x-pantalla-de-acceso titulo="Elige una contraseña nueva" bajada="Usa una frase larga que no uses en otro sitio. Con ella entrarás desde ahora.">
    <h2 class="text-[1.5rem] font-bold leading-[1.1] tracking-[-0.025em]">{{ $enlaceVigente ? 'Contraseña nueva' : 'Este enlace ya no sirve' }}</h2>

    @if (! $enlaceVigente)
        <x-aviso tono="peligro" role="alert" class="mt-5">
            El enlace para recuperar la contraseña venció o ya se usó. Cada enlace sirve una sola vez y por poco tiempo.
        </x-aviso>

        <a href="{{ route('acceso.recuperar') }}" class="mt-6 flex min-h-12 w-full items-center justify-center rounded-full bg-portada-rojo px-6 text-[17px] font-semibold text-white transition-colors duration-150 hover:bg-portada-rojo-hondo focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-portada-rojo">Pedir otro enlace</a>

        <p class="mt-6 text-center text-[14px]">
            <a href="{{ route('acceso') }}" class="font-semibold text-portada-rojo underline-offset-4 hover:underline">Volver a entrar</a>
        </p>
    @else
        @error('acceso')
            <x-aviso tono="peligro" role="alert" class="mt-5">
                {{ $message }}
                <a href="{{ route('acceso.recuperar') }}" class="font-semibold underline underline-offset-4">Pedir otro enlace</a>
            </x-aviso>
        @enderror

        <form method="POST" action="{{ route('acceso.restablecer.guardar') }}" class="mt-6 space-y-5">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            {{-- El correo va visible y de solo lectura: el enlace es de esa
                 cuenta, y el gestor de contraseñas lo necesita para guardarla. --}}
            <div>
                <label for="email" class="campo-etiqueta">Correo</label>
                <input type="email" name="email" id="email" value="{{ old('email', $email) }}" required readonly autocomplete="username"
                       class="campo rounded-xl bg-niebla py-3 text-portada-gris sm:py-3 sm:text-base">
            </div>

            <div>
                <label for="contrasena" class="campo-etiqueta">Contraseña nueva</label>
                <x-campo-de-contrasena id="contrasena" autocomplete="new-password" required autofocus minlength="{{ $longitudMinima }}" maxlength="{{ $longitudMaxima }}"
                    :describedby="'contrasena-ayuda'.($errors->has('contrasena') ? ' contrasena-error' : '')"
                    :aria-invalid="$errors->has('contrasena') ? 'true' : null" />
                <p id="contrasena-ayuda" class="campo-ayuda">Entre {{ $longitudMinima }} y {{ $longitudMaxima }} caracteres. Una frase de varias palabras es fácil de recordar y difícil de adivinar.</p>
                @error('contrasena')
                    <p id="contrasena-error" class="campo-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="contrasena_confirmation" class="campo-etiqueta">Repítela</label>
                <x-campo-de-contrasena id="contrasena_confirmation" autocomplete="new-password" required minlength="{{ $longitudMinima }}" maxlength="{{ $longitudMaxima }}" />
            </div>

            <button type="submit" class="flex min-h-12 w-full items-center justify-center rounded-full bg-portada-rojo px-6 text-[17px] font-semibold text-white transition-colors duration-150 hover:bg-portada-rojo-hondo focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-portada-rojo">Guardar la contraseña</button>
        </form>
    @endif
</x-pantalla-de-acceso>
