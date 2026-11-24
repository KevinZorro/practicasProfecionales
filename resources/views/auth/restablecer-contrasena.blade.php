{{--
    Elegir la contraseña nueva desde el enlace del correo (ADR 0011).
--}}
<x-pantalla-de-acceso titulo="Elige una contraseña nueva" bajada="Usa una frase larga que no uses en otro sitio. Con ella entrarás desde ahora.">
    <h2 class="text-[1.5rem] font-bold leading-[1.1] tracking-[-0.025em]">Contraseña nueva</h2>

    @error('acceso')
        <x-aviso tono="peligro" role="alert" class="mt-5">
            {{ $message }}
            <a href="{{ route('acceso.recuperar') }}" class="font-semibold underline underline-offset-4">Pedir otro enlace</a>
        </x-aviso>
    @enderror

    <form method="POST" action="{{ route('acceso.restablecer.guardar') }}" class="mt-6 space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <label for="email" class="campo-etiqueta">Correo</label>
            <input type="email" name="email" id="email" value="{{ old('email', $email) }}" required autocomplete="username" inputmode="email"
                   @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                   class="campo rounded-xl py-3 sm:py-3 sm:text-base">
            @error('email')
                <p id="email-error" class="campo-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="contrasena" class="campo-etiqueta">Contraseña nueva</label>
            <input type="password" name="contrasena" id="contrasena" required autofocus autocomplete="new-password" minlength="{{ $longitudMinima }}"
                   aria-describedby="contrasena-ayuda @error('contrasena') contrasena-error @enderror" @error('contrasena') aria-invalid="true" @enderror
                   class="campo rounded-xl py-3 sm:py-3 sm:text-base">
            <p id="contrasena-ayuda" class="campo-ayuda">Al menos {{ $longitudMinima }} caracteres.</p>
            @error('contrasena')
                <p id="contrasena-error" class="campo-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="contrasena_confirmation" class="campo-etiqueta">Repítela</label>
            <input type="password" name="contrasena_confirmation" id="contrasena_confirmation" required autocomplete="new-password" minlength="{{ $longitudMinima }}"
                   class="campo rounded-xl py-3 sm:py-3 sm:text-base">
        </div>

        <button type="submit" class="flex min-h-12 w-full items-center justify-center rounded-full bg-portada-rojo px-6 text-[17px] font-semibold text-white transition-colors duration-150 hover:bg-portada-rojo-hondo focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-portada-rojo">Guardar la contraseña</button>
    </form>
</x-pantalla-de-acceso>
