{{--
    Pedir el enlace para elegir una contraseña nueva (ADR 0011). Responde
    lo mismo exista o no la cuenta.
--}}
<x-pantalla-de-acceso titulo="Recupera tu contraseña" bajada="Te enviamos un enlace a tu correo para que elijas una nueva. También sirve para elegir la primera.">
    <h2 class="text-[1.5rem] font-bold leading-[1.1] tracking-[-0.025em]">¿Olvidaste tu contraseña?</h2>
    <p class="mt-2 text-[15px] leading-snug text-portada-gris">Escribe el correo de tu cuenta en la plataforma.</p>

    @if (session('estado'))
        <x-aviso tono="exito" role="status" class="mt-5">{{ session('estado') }}</x-aviso>
    @endif

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
        <a href="{{ route('acceso') }}" class="font-semibold text-portada-rojo underline-offset-4 hover:underline">Volver a entrar</a>
    </p>
</x-pantalla-de-acceso>
