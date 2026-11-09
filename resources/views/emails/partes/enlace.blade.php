{{--
    El enlace de un correo a la pantalla donde se sigue el asunto: un botón
    en tinta y, debajo, la dirección completa para el cliente de correo que
    no pinta botones. Quien no tiene la sesión abierta entra con Google y
    vuelve aquí (AccesoConGoogleController lleva a url.intended).

    Las direcciones salen de APP_URL: en la cola no hay petición de la que
    tomarlas.
--}}
<p style="margin: 24px 0 8px;">
    <a href="{{ $url }}" style="display: inline-block; background: #1d1d1f; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 999px; font-weight: 600;">{{ $texto }}</a>
</p>
<p style="margin: 0 0 24px; font-size: 13px; color: #6e6e73;">Si el botón no abre, copie esta dirección en el navegador: {{ $url }}</p>
