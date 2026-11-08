# 0002. Copiar los usuarios de una vista de la base institucional

- Fecha: 2026-11-07 (registrada después de implementarse la sincronización; la fuente real sigue pendiente)
- Estado: Aceptada

## Contexto

Según el RF19 y el RF20, los usuarios vienen de la base institucional (Divisist), y el acceso depende de la vigencia institucional (regla 8 de `CLAUDE.md`). La UFPS entregará una vista de su base con los campos que se le pidieron. El 2026-11-07 todavía no la había entregado (pendiente 2 de `CLAUDE.md`).

## Decisión

La plataforma no consulta Divisist en cada entrada: copia las personas a su propia tabla `users` con el comando `usuarios:sincronizar`.

- **Puerto y adaptadores.** La fuente es un puerto, `FuenteInstitucional`, que traduce las columnas de la universidad a `PersonaInstitucional`. Hoy solo existe la fuente simulada, un archivo JSON. La real será otro adaptador que lea la vista por red, con un usuario de solo lectura, TLS y tiempo máximo, y se elegirá con `SINCRONIZACION_FUENTE`.
- **Lo que la sincronización no hace.** No borra a nadie, no toca las cuentas `manual` ni la marca `deshabilitado_at` del ADMIN, solo da roles permanentes y nunca revoca.
- **Freno de seguridad.** Se detiene sin aplicar nada si una pasada fuera a desactivar más del umbral (10 %).
- **Frecuencia.** Al menos al comienzo de cada semestre, y a diario si se activa `SINCRONIZACION_PROGRAMADA`.

## Alternativas consideradas

- **Consultar la vista en cada entrada.** La entrada dependería de que Divisist responda.
- **Cargar a mano un archivo cada semestre.** No hace falta conexión de red, pero todo depende de una persona.
- **Leer las tablas de Divisist en vez de una vista.** Acoplaría la plataforma al esquema interno de la universidad.

## Consecuencias

- Gana: que Divisist esté caído o lento no afecta la entrada ni las pantallas.
- Gana: si la UFPS cambia las columnas de la vista, solo se rompe el adaptador.
- Sacrifica: quien se matricula o se vincula después de la última pasada no tiene cuenta hasta la siguiente. Mientras tanto, el ADMIN puede crearle una cuenta `manual`.
- Sacrifica: quien se retira conserva el acceso hasta la siguiente pasada.
- Divisist pasa a ser un actor externo de la plataforma, y la conexión con él cruza la red, así que tiene que ir cifrada.

**Pendiente con la UFPS:** el motor de la base, el contrato de la vista (columnas, tipos y filtro por programa), si trae el documento de identidad y la frecuencia acordada.

**Señal de revisión:** si en un semestre el ADMIN crea a mano más de 40 cuentas de personas que la vista ya traía (cerca del 5 % de 850), la frecuencia sube a diaria. Si la vista no llega, se vuelve a la carga de un archivo con otro adaptador del mismo puerto.
