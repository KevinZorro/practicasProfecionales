<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Tu contraseña cambió</title>
</head>
<body style="font-family: sans-serif; color: #1f2937; line-height: 1.5;">
    <p>Buen día, {{ $destinatario->nombre }}:</p>

    <p>La contraseña de tu cuenta en la plataforma del laboratorio acaba de cambiar.
       Desde ahora se entra con la nueva, y las sesiones abiertas en otros equipos se cerraron.</p>

    <p>Si fuiste tú, no tienes que hacer nada. Si no fuiste tú, elige otra ahora mismo en
       <a href="{{ route('acceso.recuperar') }}" style="color: #d30f23; font-weight: 600;">{{ route('acceso.recuperar') }}</a>
       y avísale al laboratorio.</p>

    <p>{{ config('app.name') }}</p>
</body>
</html>
