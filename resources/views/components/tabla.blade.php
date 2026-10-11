{{--
    Tabla en una pieza blanca. En el celular se desplaza en horizontal dentro
    de su pieza en vez de romper la página: el administrativo la consulta
    desde el celular. Las cifras van tabulares para que las columnas cuadren.
--}}
@props(['encabezados' => []])

<div {{ $attributes->class('overflow-x-auto rounded-2xl bg-white') }}>
    <table class="min-w-full text-left text-[14px] tabular-nums">
        @if ($encabezados !== [])
            <thead>
                <tr class="border-b border-gray-200">
                    @foreach ($encabezados as $encabezado)
                        <th scope="col" class="whitespace-nowrap px-4 py-3 text-[13px] font-semibold text-gray-500 first:pl-5 last:pr-5">
                            {{ $encabezado }}
                        </th>
                    @endforeach
                </tr>
            </thead>
        @endif

        <tbody class="divide-y divide-gray-100 [&_td:first-child]:pl-5 [&_td:last-child]:pr-5 [&_td]:px-4 [&_td]:py-3 [&_tr]:transition-colors [&_tr:hover]:bg-gray-50/70">
            {{ $slot }}
        </tbody>
    </table>
</div>
