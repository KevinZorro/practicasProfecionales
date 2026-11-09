@php($solicitud = $preparacion->solicitud)
@php($dicta = $solicitud->docenteQueDicta ?? $solicitud->docente)
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $esCambio ? 'Cambio de sala' : 'Sala asignada' }}</title>
</head>
<body style="font-family: sans-serif; color: #1f2937; line-height: 1.5;">
    <p>Buen día, {{ $dicta->nombre }}:</p>

    @php($unidas = $preparacion->salas->count() > 1)

    @if ($esCambio && $unidas)
        <p>Las salas de su sesión <strong>cambiaron</strong>. Ahora son:</p>
    @elseif ($esCambio)
        <p>La sala de su sesión <strong>cambió</strong>. La nueva es:</p>
    @else
        <p>Su sesión ya tiene {{ $unidas ? 'salas' : 'sala' }}:</p>
    @endif

    @foreach ($preparacion->salas as $sala)
        <p style="font-size: 1.25em; margin: 0.25em 0;"><strong>{{ $sala->nombreCompleto() }}</strong></p>
    @endforeach

    @if ($unidas)
        {{-- P16: unidas abriendo las cortinas, son un solo salón. --}}
        <p>Las salas se unen abriendo la cortina que las separa.</p>
    @endif

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
            <td><strong>Fecha</strong></td>
            <td>{{ $solicitud->fecha->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td><strong>Hora</strong></td>
            <td>{{ substr($solicitud->hora_inicio, 0, 5) }} a {{ substr($solicitud->hora_fin, 0, 5) }}</td>
        </tr>
    </table>

    @include('emails.partes.enlace', ['url' => route('panel.solicitudes.ver', $solicitud), 'texto' => 'Ver la sesión'])

    @include('emails.partes.firma')
</body>
</html>
