# 0001. Entrar solo con la cuenta institucional de Google

- Fecha: 2026-11-07 (registrada después de implementarse)
- Estado: Aceptada

## Contexto

El RF18 pide entrar con la cuenta institucional de Google, y la UFPS usa Google Workspace. Son unas 850 personas (RNF01) y un solo mantenedor. Los egresados conservan el correo institucional, así que el correo por sí solo no prueba que alguien siga vinculado (regla 8 de `CLAUDE.md`).

## Decisión

La única entrada es Google OAuth, con Laravel Socialite. No existe `users.password` ni hay registro o recuperación de contraseña.

- **Entra quien ya tiene cuenta en `users`.** La crean la sincronización institucional o el ADMIN; entrar no crea cuentas.
- **La primera vez se vincula `google_id`.** Desde entonces la cuenta se encuentra por él, aunque cambie el correo.
- **Lo que se comprueba al volver de Google:** `email_verified` y el dominio, con el campo `hd` (`GOOGLE_DOMINIO_INSTITUCIONAL`). Sin dominio configurado, en producción no se entra.
- **La llamada a Google tiene tiempo máximo:** 5 s, y 3 s para conectar. Si Google falla, la persona vuelve a la entrada con un mensaje (B3.1).

Quién entra lo decide `AccesoService`, que no conoce Socialite y recibe una `IdentidadDeGoogle`.

## Alternativas consideradas

- **Contraseñas propias (Breeze).** Habría que custodiar hashes, ofrecer recuperación por correo, frenar la fuerza bruta y dar soporte a quien olvida la clave.
- **El inicio de sesión único de la universidad por SAML o CAS.** No se ofreció.
- **Crear la cuenta al entrar con cualquier correo del dominio.** Entrarían los egresados, que conservan el correo.

## Consecuencias

- Gana: no hay contraseñas que custodiar, y el segundo factor lo pone Google Workspace según la política de la UFPS (sin confirmar).
- Sacrifica: si Google no responde, solo siguen quienes ya tenían sesión, durante 120 minutos sin actividad.
- Sacrifica: el nombre del proveedor llega al dominio (`google_id`, `AccesoService::cuentaDeGoogle`). Cambiar de proveedor obliga a tocar el Service, la columna y las pruebas.
- Sacrifica: quien no tiene cuenta no entra hasta la siguiente sincronización o hasta que el ADMIN se la cree.

**Señal de revisión:** que la UFPS cambie de proveedor de identidad o exija otro mecanismo de entrada.
