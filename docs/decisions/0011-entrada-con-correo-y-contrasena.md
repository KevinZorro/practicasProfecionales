# 0011. Entrar también con correo y contraseña

- Fecha: 2026-11-24
- Estado: Aceptada. Reemplaza en parte a la 0001: Google sigue, pero deja de ser la única entrada

## Contexto

La 0001 dejó a Google como única entrada. Google exige que la dirección de retorno sea `https://` y un nombre, o `localhost`, y el servidor del laboratorio todavía se usa por IP y sin HTTPS: mientras eso no cambie, nadie puede entrar a la plataforma instalada, ni siquiera el ADMIN. El desarrollador pidió una entrada con correo y contraseña para la aplicación, no solo para el sandbox.

## Decisión

Se entra con correo y contraseña en `/acceso`, junto a Google. La pantalla ofrece las dos entradas que estén disponibles.

- **La contraseña se elige con un enlace al correo** (`/acceso/recuperar`): vale 60 minutos, sirve una vez, se guarda cifrado en `password_reset_tokens` y para pedir otro al mismo correo hay que esperar cada vez más: 1, 3, 5, 10, 15, 30 y 60 minutos, y vuelve a empezar tras un día sin pedirlo (`EsperaDeReenvio`, por correo y no por IP). Después de pedirlo, la pantalla dice a qué correo salió y que se revise el spam; la espera no se muestra (lo pidió el usuario), solo se avisa si alguien se adelanta. Sirve también para la primera contraseña: recibirlo en el buzón institucional prueba que el correo es de quien lo pide. La pantalla responde lo mismo exista o no la cuenta, y una inactiva o deshabilitada no recibe nada.
- **Quien tiene la consola también la fija:** `php artisan usuarios:contrasena correo`, que la pregunta dos veces sin mostrarla, o con `--generar` inventa una de 16 caracteres y la muestra una vez. Sirve mientras el correo no salga. Entre 12 caracteres y 72 bytes en los dos caminos: bcrypt ignora lo que pase de 72 bytes, así que se rechaza en vez de cortarla en silencio. No hay registro: las cuentas las siguen creando la sincronización y el ADMIN.
- **Una cuenta sin contraseña solo entra con Google.** Así nacen las que trae la sincronización; la columna `users.password` es nullable y se guarda con bcrypt (cast `hashed`).
- **Después de la contraseña se aplica la misma regla 8** (`AccesoService::puedeEntrar()`): una cuenta inactiva o deshabilitada no entra aunque la contraseña sea correcta.
- **Un solo mensaje para correo inexistente, cuenta sin contraseña y contraseña equivocada**, y el mismo tiempo de respuesta, para no delatar qué correos tienen cuenta.
- **Dos topes de intentos fallidos** (`IntentosDeEntrada`). Cinco por minuto por correo e IP juntos: solo por IP, toda la universidad (que sale por la misma dirección) quedaría sin entrar por culpa de una persona. Y veinte en 15 minutos por correo, venga de la IP que venga, contra quien prueba una cuenta repartiendo los intentos entre muchas IP; queda un aviso en el registro, con el correo y la IP, nunca la contraseña. Ese segundo tope no deja a la persona por fuera: sigue entrando con Google o con el enlace del correo.
- **Cambiar la contraseña cierra las demás sesiones y avisa por correo.** `AuthenticateSession` va en el grupo web: cada sesión guarda el hash con el que entró y sale en su siguiente petición si ya no coincide; `remember_token` se borra. El aviso (`ContrasenaCambiadaMail`) sale después de confirmar, por la consola o por el enlace: si no fue la persona, se entera. A una cuenta sin contraseña (solo Google) no la afecta.
- **El enlace del correo se comprueba al abrirlo**, antes de pedir la contraseña nueva, y se usa con la cuenta bloqueada (`lockForUpdate`): dos envíos a la vez del mismo enlace no lo usan dos veces. Pedirlo para un correo sin cuenta tarda lo mismo que para uno con cuenta.
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
