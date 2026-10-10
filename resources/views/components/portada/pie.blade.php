{{--
    El logo horizontal lleva «Vigilada Mineducación», que toda institución
    de educación superior vigilada debe mostrar.
--}}
<footer class="border-t border-portada-linea/70 bg-portada-niebla">
    <div class="mx-auto max-w-[1180px] px-4 py-14 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-10 border-b border-portada-linea pb-10 md:flex-row md:items-end md:justify-between">
            <img src="{{ asset('marca/ufps-horizontal.webp') }}"
                 alt="Universidad Francisco de Paula Santander. Vigilada Mineducación"
                 width="800" height="159" loading="lazy" decoding="async"
                 class="h-auto w-64 mix-blend-multiply sm:w-72">

            <p class="max-w-sm text-sm leading-relaxed text-portada-gris">
                Laboratorio de Simulación Clínica de la Facultad de Ciencias de la Salud.
            </p>
        </div>

        <div class="flex flex-col gap-4 pt-6 text-xs text-portada-gris sm:flex-row sm:items-center sm:justify-between">
            <p>© {{ now()->year }} Universidad Francisco de Paula Santander</p>
            <a href="{{ route('panel.inicio') }}"
               class="font-medium text-portada-tinta underline-offset-4 hover:underline focus-visible:rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-portada-rojo">
                Ingresar a la plataforma
            </a>
        </div>
    </div>
</footer>
