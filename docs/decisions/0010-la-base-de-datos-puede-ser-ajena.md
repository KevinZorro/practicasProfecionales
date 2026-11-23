# 0010. La base de datos puede ser una que ya existe

- Fecha: 2026-11-23
- Estado: Aceptada

## Contexto

El ADR 0003 decidió PostgreSQL en el compose, con su servicio `db`, y el instalador (ADR 0009) la daba por hecha. Pero el ingeniero de la universidad preparó el sandbox con una base PostgreSQL propia, vacía, con un usuario que no es superusuario, y la universidad prefiere que la plataforma use esa. Cuatro cosas dependían de que la base fuera nuestra:

1. El servicio `db` arrancaba siempre.
2. El usuario de la aplicación (S.1) se crea con `CREATE ROLE`, que exige `CREATEROLE`; el dueño de la base del compose es superusuario, el de una base ajena puede no serlo.
3. `pg_dump` falla si el cliente es más viejo que el servidor, y el servicio `copias` traía la 16.
4. Nada impedía instalar sobre una base con datos de otra instalación.

## Decisión

1. **`db` es opcional.** Va en el perfil `base-propia` y los demás servicios lo esperan con `required: false`. El `.env` lo dice con `COMPOSE_PROFILES`; la base es la del compose cuando `DB_HOST=db`. El valor por defecto no cambia.
2. **`produccion.sh instalar` pregunta por la base** y, si es ajena, entra a ella desde un contenedor, como lo hará la aplicación, y revisa lo que decide cómo seguir. No cambia nada en la base.
3. **El cliente de las copias sigue la versión de la base:** `IMAGEN_CLIENTE_POSTGRES=postgres:N-alpine`.
4. **Usuario aparte si se puede, el mismo si no.** Con `CREATEROLE`, todo sigue como en el ADR 0003 y S.1. Sin él, `DB_USUARIO_UNICO=true`: la aplicación entra con el usuario que migra. Es un opt-in explícito que el instalador escribe y avisa; sin él, el mismo usuario sigue siendo un error.
5. **Nunca borra nada de una base ajena.** Si tiene tablas y el `.env` es nuevo, se detiene, porque con otra `APP_KEY` los documentos cifrados no se leerían.

## Alternativas consideradas

- **Dejar `db` siempre y apuntar `DB_HOST` a la ajena.** Un PostgreSQL sin uso corriendo, que se confunde con el verdadero.
- **Un segundo archivo de compose para la base ajena.** Dos archivos que se desfasan, y los comandos de la documentación tendrían que saber cuál usar.
- **Exigir siempre un usuario aparte.** Habría bloqueado el sandbox por un permiso que el ingeniero tal vez no quiera dar.

## Consecuencias

- Gana: la plataforma se instala sobre la base que la universidad ya administra, con las mismas copias y restauración.
- Sacrifica: con `DB_USUARIO_UNICO` se pierde el límite de S.1: una inyección de SQL podría cambiar la estructura de la base. Para producción conviene dar `CREATEROLE` al usuario.
- Sacrifica: Docker Compose mínimo 2.20.2 (`required: false`); el instalador lo comprueba. No se probó con la 2.26.1 que trae Debian 13, solo con la 5.7.
- Las copias y la base quedan donde las administre la universidad: si la base está en otro servidor, las copias no la protegen de perder ese servidor (ADR 0007).

**Señal de revisión:** que la universidad entregue una base con un usuario ya limitado y pida no crear roles; entonces el instalador tendría que aceptar un usuario de la aplicación ya existente.
