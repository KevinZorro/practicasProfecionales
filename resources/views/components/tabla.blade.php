{{--
    Tabla con cabecera. En móvil se desplaza en horizontal dentro de su
    contenedor en vez de romper la página, que es lo que el administrativo
    necesita cuando la consulta desde el celular.
--}}
@props(['encabezados' => []])

<div {{ $attributes->class('overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm') }}>
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        @if ($encabezados !== [])
            <thead class="bg-gray-50">
                <tr>
                    @foreach ($encabezados as $encabezado)
                        <th scope="col" class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                            {{ $encabezado }}
                        </th>
                    @endforeach
                </tr>
            </thead>
        @endif

        <tbody class="divide-y divide-gray-100">
            {{ $slot }}
        </tbody>
    </table>
</div>
