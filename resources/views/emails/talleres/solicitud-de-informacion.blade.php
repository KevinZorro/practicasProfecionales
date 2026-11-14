@php($taller = $solicitud->taller)
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Solicitud de información</title>
</head>
<body style="font-family: sans-serif; color: #1f2937; line-height: 1.5;">
    <p>Desde la portada del laboratorio piden información sobre el taller <strong>{{ $taller->titulo }}</strong> ({{ $taller->fecha->translatedFormat('j \d\e F \d\e Y') }}).</p>

    <table cellpadding="6" cellspacing="0" style="border-collapse: collapse;">
        <tr>
            <td><strong>Nombre</strong></td>
            <td>{{ $solicitud->nombre }}</td>
        </tr>
        <tr>
            <td><strong>Correo</strong></td>
            <td>{{ $solicitud->email }}</td>
        </tr>
        @if ($solicitud->telefono)
            <tr>
                <td><strong>Teléfono</strong></td>
                <td>{{ $solicitud->telefono }}</td>
            </tr>
        @endif
    </table>

    @if ($solicitud->mensaje)
        <p><strong>Mensaje</strong></p>
        <p style="white-space: pre-line;">{{ $solicitud->mensaje }}</p>
    @endif

    <p style="color: #6b7280;">Para contestar, responda este correo: la respuesta le llega a {{ $solicitud->nombre }}.</p>
</body>
</html>
