@php($solicitud = $reprogramacion->solicitud)
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Sesión reprogramada</title>
</head>
<body style="font-family: sans-serif; color: #1f2937; line-height: 1.5;">
    <p>Buen día:</p>

    <p>Como se le comunicó, su sesión de <strong>{{ $solicitud->materia->nombre }}</strong> fue reprogramada.</p>

    <table cellpadding="6" cellspacing="0" style="border-collapse: collapse;">
        <tr>
            <th align="left"></th>
            <th align="left">Antes</th>
            <th align="left">Ahora</th>
        </tr>
        <tr>
            <td><strong>Fecha</strong></td>
            <td>{{ $reprogramacion->fecha_anterior->format('d/m/Y') }}</td>
            <td>{{ $reprogramacion->fecha_nueva->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td><strong>Hora</strong></td>
            <td>{{ substr($reprogramacion->hora_inicio_anterior, 0, 5) }} a {{ substr($reprogramacion->hora_fin_anterior, 0, 5) }}</td>
            <td>{{ substr($reprogramacion->hora_inicio_nueva, 0, 5) }} a {{ substr($reprogramacion->hora_fin_nueva, 0, 5) }}</td>
        </tr>
        <tr>
            <td><strong>Caso clínico</strong></td>
            <td>{{ $reprogramacion->casoClinicoAnterior->nombre }}</td>
            <td>{{ $reprogramacion->casoClinicoNuevo->nombre }}</td>
        </tr>
    </table>

    <p><strong>Motivo:</strong> {{ $reprogramacion->motivo }}</p>

    <p>La sala se le confirmará en otro correo cuando el laboratorio la asigne.</p>

    <p>{{ config('app.name') }}</p>
</body>
</html>
