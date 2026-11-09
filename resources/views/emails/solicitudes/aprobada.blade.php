<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Solicitud aprobada</title>
</head>
<body style="font-family: sans-serif; color: #1f2937; line-height: 1.5;">
    <p>Buen día, {{ $solicitud->docente->nombre }}:</p>

    <p>Su solicitud de escenario fue <strong>aprobada</strong>.</p>

    <table cellpadding="6" cellspacing="0" style="border-collapse: collapse;">
        <tr>
            <td><strong>Escenario clínico</strong></td>
            <td>{{ $solicitud->casoClinico->nombre }}</td>
        </tr>
        <tr>
            <td><strong>Materia</strong></td>
            <td>{{ $solicitud->materia->nombre }}</td>
        </tr>
        <tr>
            <td><strong>Tipo de sesión</strong></td>
            <td>{{ $solicitud->tipo->etiqueta() }}</td>
        </tr>
        <tr>
            <td><strong>Fecha</strong></td>
            <td>{{ $solicitud->fecha->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td><strong>Hora</strong></td>
            <td>{{ substr($solicitud->hora_inicio, 0, 5) }} a {{ substr($solicitud->hora_fin, 0, 5) }}</td>
        </tr>
        <tr>
            <td><strong>Estudiantes</strong></td>
            <td>{{ $solicitud->cantidad_estudiantes }}</td>
        </tr>
    </table>

    @if (filled($solicitud->observaciones_aprobacion))
        {{-- Obligatorias si faltan unidades: lo que no va a encontrar. --}}
        <p><strong>Observaciones de la aprobación:</strong> {{ $solicitud->observaciones_aprobacion }}</p>
    @endif

    {{-- La sala se asigna durante la preparación (RF36), y llega en otro correo. --}}
    <p>La sala la elige el laboratorio al preparar el escenario. Le llegará
       otro correo en cuanto la asigne.</p>

    @include('emails.partes.enlace', ['url' => route('panel.solicitudes.ver', $solicitud), 'texto' => 'Ver la solicitud'])

    <p>{{ config('app.name') }}</p>
</body>
</html>
