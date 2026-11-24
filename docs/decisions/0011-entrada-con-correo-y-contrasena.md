# 0011. Entrar también con correo y contraseña

- Fecha: 2026-11-24
- Estado: Aceptada. Reemplaza en parte a la 0001: Google sigue, pero deja de ser la única entrada

## Contexto

La 0001 dejó a Google como única entrada. Google exige que la dirección de retorno sea `https://` y un nombre, o `localhost`, y el servidor del laboratorio todavía se usa por IP y sin HTTPS: mientras eso no cambie, nadie puede entrar a la plataforma instalada, ni siquiera el ADMIN. El desarrollador pidió una entrada con correo y contraseña para la aplicación, no solo para el sandbox.

## Decisión

Se entra con correo y contraseña en `/acceso`, junto a Google. La pantalla ofrece las dos entradas que estén disponibles.

- **La contraseña se elige con un enlace al correo** (`/acceso/recuperar`): vale 60 minutos, sirve una vez, se guarda cifrado en `password_reset_tokens` y no se manda otro a la misma cuenta antes de un minuto. Sirve también para la primera contraseña: recibirlo en el buzón institucional prueba que el correo es de quien lo pide. La pantalla responde lo mismo exista o no la cuenta, y una inactiva o deshabilitada no recibe nada.
- **Quien tiene la consola también la fija:** `php artisan usuarios:contrasena correo`, que la pregunta dos veces sin mostrarla, o con `--generar` inventa una de 16 caracteres y la muestra una vez. Sirve mientras el correo no salga. Mínimo 12 caracteres en los dos caminos. No hay registro: las cuentas las siguen creando la sincronización y el ADMIN.
- **Una cuenta sin contraseña solo entra con Google.** Así nacen las que trae la sincronización; la columna `users.password` es nullable y se guarda con bcrypt (cast `hashed`).
- **Después de la contraseña se aplica la misma regla 8** (`AccesoService::puedeEntrar()`): una cuenta inactiva o deshabilitada no entra aunque la contraseña sea correcta.
- **Un solo mensaje para correo inexistente, cuenta sin contraseña y contraseña equivocada**, y el mismo tiempo de respuesta, para no delatar qué correos tienen cuenta.
- **Cinco intentos por minuto por correo e IP juntos.** Solo por IP, toda la universidad (que sale por la misma dirección) quedaría sin entrar por culpa de una persona.
- **Se apaga con `ENTRADA_CON_CONTRASENA=false`**, por si la universidad exige más adelante solo su cuenta institucional. Entonces la pantalla solo ofrece Google.

## Alternativas consideradas

- **Enlace mágico por correo, sin contraseña.** Cada entrada dependería del correo saliente, que todavía no está configurado (pendiente 6).
- **Un enlace de un solo uso generado en la consola.** Resuelve la entrada del ADMIN, no la de los demás usuarios.
- **Breeze.** Trae registro, verificación y recuperación que no se usan, y vistas que habría que rehacer con el diseño del panel.

## Consecuencias

- Gana: la plataforma se puede usar sin HTTPS ni credenciales de Google, por IP o por túnel.
- Sacrifica: hay que custodiar hashes de contraseña. Las copias de la base los llevan.
- Sacrifica: la recuperación depende del correo saliente. Mientras `MAIL_MAILER=log`, el enlace queda en `storage/logs` y la contraseña la fija la consola.
- Sacrifica: sin HTTPS la contraseña viaja sin cifrar por la red de la universidad. Sirve para el sandbox; en producción va detrás del HTTPS del proxy (ADR 0006).
- Pendiente: que cada persona cambie su contraseña desde el panel sin pasar por el correo.

**Señal de revisión:** que haya HTTPS y Google funcione para todos (¿se apaga la contraseña?), o que la universidad pida otro mecanismo de entrada.
