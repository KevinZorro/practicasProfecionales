# 0010. Despliegue nativo sin Docker, con la base en otra máquina

- Fecha: 2026-11-23
- Estado: Aceptada. Complementa a la 0009: el camino con Docker sigue siendo el de desarrollo y el de la CI.

## Contexto

El administrador del servidor sandbox no permite Docker. Lo que hay es un Debian 13 con PHP 8.3-FPM, Composer, Node, nginx y el cliente de PostgreSQL; PostgreSQL corre en otra máquina (`172.18.222.232`, base `laboratorio`, usuario `testlab`). El usuario que despliega no tiene `sudo` ni está en el grupo `www-data`.

El compose de producción hacía cuatro cosas que ahora no hay quién haga: servir con nginx, correr la cola y el programador, guardar los archivos subidos en volúmenes y hacer la copia diaria.

## Decisión

1. **`nativo.sh` y `.env.nativo.example`**, junto a `produccion.sh`, instalan y actualizan sin `sudo` y sin Docker: Composer sin dev, Vite, migraciones, `optimize`, `storage:link` y permisos. Guía: `docs/despliegue-nativo.md`.
2. **La cola y el programador corren por el cron del usuario que despliega** (`queue:work --max-time=3600` bajo `flock`, y `schedule:run`), no por servicios systemd, que exigen root. Si el administrador prefiere systemd, la guía trae las unidades.
3. **Todo sigue en PostgreSQL** (sesión, caché y cola en `database`): no se introduce Redis ni otro servicio.
4. **`DB_HOST` es una variable del `.env`**; el código no tenía hosts fijos (se revisó `app/`, `config/`, `routes/` y `resources/views`).
5. **El usuario de solo filas (S.1) es opcional.** Con `DB_DUENO_USUARIO` las migraciones corren con el dueño y la aplicación entra con otro usuario creado por `base:usuario-de-la-aplicacion` (el dueño necesita `CREATEROLE`). Sin él, todo corre con `DB_USERNAME`, que tiene que ser dueño de las tablas. Es lo normal en el sandbox, donde `testlab` es un solo usuario; para producción se recomienda pedir el dueño con `CREATEROLE`.
6. **Los registros van a `storage/logs/` (`LOG_CHANNEL=daily`)**, y `LOG_PERMISOS` fija el permiso de los archivos porque los escriben dos usuarios del sistema (PHP-FPM y el cron). Vacío, el comportamiento es el de siempre.
7. **Las copias** las hace `nativo.sh copia` con `pg_dump` en el mismo formato que `docker/produccion/copias/`, así que se restauran igual.
8. **Los topes de subida de los videos** (32M y 36M) van en `public/.user.ini`, que PHP-FPM lee sin tocar el `php.ini` del servidor. `client_max_body_size 36M` lo pone el administrador en nginx.

## Alternativas consideradas

- **Systemd para la cola y el programador:** más robusto, pero necesita root. Queda como opción en la guía.
- **Un instalador de PHP en el usuario (compilar o PHAR):** más superficie para mantener que pedir los paquetes del sistema.
- **Reemplazar el compose:** no. Desarrollo y CI lo siguen usando, y `ConfiguracionDeProduccionTest` lo vigila.

## Consecuencias

- Dos caminos de despliegue que mantener. `DespliegueNativoTest` vigila lo que se rompería en silencio, pero no corre `nativo.sh` entero: la CI no tiene un Debian con PHP-FPM.
- Sin `www-data` en el grupo del usuario, `storage` y `bootstrap/cache` quedan escribibles por todos, y la configuración en caché (con las contraseñas) legible por todos. En un servidor compartido se corrige añadiendo al usuario al grupo `www-data`; `nativo.sh` lo detecta y usa permisos de grupo.
- Sin HTTPS no hay entrada con Google (exige un dominio y `https`), y la cookie de sesión no puede ser `secure`: ver ADR 0006.
