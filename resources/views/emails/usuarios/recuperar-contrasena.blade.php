<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recupera tu contraseña</title>
</head>
<body style="font-family: sans-serif; color: #1f2937; line-height: 1.5;">
    <p>Buen día, {{ $destinatario->nombre }}:</p>

    <p>Alguien pidió recuperar la contraseña de tu cuenta en la plataforma del laboratorio.
       Para elegir una nueva, abre este enlace:</p>

    <p><a href="{{ $enlace }}" style="color: #d30f23; font-weight: 600;">Elegir una contraseña nueva</a></p>

    <p>Vale {{ $minutosDeVigencia }} minutos y sirve una sola vez. Si no fuiste tú, ignora este correo:
       tu contraseña sigue igual.</p>

    <p>{{ config('app.name') }}</p>
</body>
</html>
