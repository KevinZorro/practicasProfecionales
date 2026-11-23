# Instalación en el servidor

Cómo se instala la plataforma en un servidor de la universidad —el sandbox de pruebas o el de producción, es el mismo procedimiento— con un solo comando: `./produccion.sh instalar`. La sección «Producción» del `README.md` tiene los mismos pasos hechos a mano, para quien quiera entender cada uno.

El script corre en el servidor, no en el equipo de desarrollo, y solo necesita Docker. Imprime cada orden de Docker antes de correrla, con `$` delante, y se puede repetir sin miedo: no cambia las claves de un `.env` que ya existe y deja como está lo que ya se hizo.

---

## 1. Antes de ir: lo que hay que pedirle al ingeniero

Lo imprescindible para instalar:

- [ ] **Acceso por SSH** con un usuario que tenga `sudo`, o que ya esté en el grupo `docker`.
- [ ] **Docker Engine 24 o superior, Docker Compose v2 y git.** Se comprueba con `docker --version`, `docker compose version` (o `docker-compose version`, si es el paquete de Debian) y `git --version`. Si no están, la sección 2 tiene cómo instalarlos.
- [ ] **Salida a internet desde el servidor** mientras se instala, a estos sitios: Docker Hub (`registry-1.docker.io`, `auth.docker.io`, `production.cloudflare.docker.com`), `deb.debian.org` (también por el puerto 80), `repo.packagist.org`, `github.com`, `api.github.com`, `codeload.github.com` y `registry.npmjs.org`. Si la salida pasa por un proxy, su dirección y puerto (sección 7). **Si el servidor no sale a internet, se usa el plan B** (sección 6): conviene llevarlo preparado por si acaso.
- [ ] **Un puerto libre**, el 80 si se puede, y desde dónde se podrá abrir la plataforma (la red de la universidad, una VPN).
- [ ] **Espacio en disco:** al menos 6 GB libres para instalar; se recomiendan 20 GB, porque los videos y las copias de seguridad crecen.
- [ ] **Memoria:** 2 GB de RAM como mínimo; 4 GB van holgados.

Lo que se puede dejar para después: la plataforma se instala sin ello. Google y el correo, el script los vuelve a preguntar en cada pasada mientras falten; la dirección y el proxy se cambian en el `.env`.

- [ ] **Nombre y HTTPS.** El subdominio (por ejemplo `laboratorio.ufps.edu.co`) y quién pone el HTTPS. Si lo pone el proxy de la universidad (ADR 0006), **la IP de ese proxy**, que va en `PROXIES_DE_CONFIANZA`.
- [ ] **Credenciales de Google** para la entrada (sección 4). Sin ellas nadie puede iniciar sesión, pero la portada pública funciona.
- [ ] **El correo del laboratorio y su contraseña de aplicación** (sección 5). Sin ellos los avisos quedan escritos en el registro en vez de enviarse.
- [ ] **Salida desde el servidor a `www.googleapis.com` (443) y a `smtp.gmail.com` (587)**, ya con la plataforma andando: la primera la usa la entrada con Google y la segunda, el correo.

---

## 2. Lo que necesita el servidor

Si Docker ya está, se salta esta sección. Si no, en Debian 13 lo recomendado es el repositorio oficial de Docker (los pasos al día están en <https://docs.docker.com/engine/install/debian/>):

```bash
sudo apt-get update
sudo apt-get install -y ca-certificates curl git
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/debian/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/debian $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
```

Y para usar Docker sin `sudo` (hay que salir y volver a entrar por SSH para que valga):

```bash
sudo usermod -aG docker "$USER"
```

Usa siempre el mismo usuario para instalar y para mantener: el `.env` queda legible solo para quien lo creó.

---

## 3. El comando rápido

En el servidor, por SSH:

```bash
sudo mkdir -p /srv/laboratorio && sudo chown "$USER": /srv/laboratorio
```

Sin `sudo`, clona en tu carpeta personal (`git clone ... ~/laboratorio`): el instalador guarda las copias dentro del clon.

```bash
git clone https://github.com/KevinZorro/practicasProfecionales.git /srv/laboratorio
```

```bash
cd /srv/laboratorio && ./produccion.sh instalar
```

La primera vez tarda entre 10 y 20 minutos, casi todo en construir las imágenes. Lo que pregunta, y qué contestar:

| Pregunta | Qué poner |
|---|---|
| Dirección | Con HTTPS si ya lo hay (`https://laboratorio.ufps.edu.co`). Si todavía no, la que propone: la IP del servidor con `http://`. |
| IP del proxy | Solo si la dirección es `https://` y el HTTPS lo pone un proxy. Vacío si no se sabe todavía. |
| `GOOGLE_CLIENT_ID` y lo demás de Google | Vacío si todavía no hay credenciales. |
| Correo del laboratorio | Vacío si todavía no hay contraseña de aplicación. |
| ¿Usar una base PostgreSQL que ya existe? | `s` si el ingeniero preparó una (sección «La base de datos»). Con `N`, la instalación trae la suya. |
| Correo y nombre del ADMIN | El correo institucional de quien va a administrar la plataforma. |

Y lo que hace, en orden:

1. Comprueba Docker, Compose, el disco, la memoria y que el puerto esté libre (si está ocupado, pregunta otro).
2. Arma el `.env` desde `.env.produccion.example`: genera la `APP_KEY` y las dos contraseñas de la base, llena lo que se le contestó y lo deja con permisos 600. Sin HTTPS apaga la cookie segura, que si no viajaría y nadie podría iniciar sesión.
3. Crea la carpeta de las copias de seguridad, solo para su dueño.
4. Construye las imágenes y descarga la de PostgreSQL.
5. Corre las migraciones con el dueño de la base, crea los seis roles y el usuario con el que entra la aplicación (sin superusuario).
6. Levanta los servicios y espera a que `/up` responda.
7. Crea el primer ADMIN con `usuarios:crear-administrador`: la entrada con Google no crea cuentas, así que sin este paso nadie podría entrar.
8. Dice qué falta para usarla de verdad: HTTPS, Google, correo.

### La base de datos: la propia o una que ya existe

Por defecto la instalación levanta su propia PostgreSQL en Docker (el servicio `db`) y no hace falta nada más. Si la universidad ya preparó una base, el instalador la usa y **no levanta la suya** (ADR 0010). Necesita cinco datos: dirección, puerto, nombre de la base, usuario y contraseña.

- **La dirección no puede ser `localhost`:** dentro de Docker eso es el propio contenedor. Si PostgreSQL está en el mismo servidor, usa su IP (`hostname -I`).
- **El instalador entra a la base desde un contenedor, como lo hará la aplicación,** y revisa: la versión (las copias de seguridad usan el cliente de la misma versión), si el usuario puede crear tablas y roles, y si la base está vacía. **Nunca borra nada de ella:** si ya tiene tablas y el `.env` es nuevo, se detiene.
- **Si no puede entrar,** dice por qué y borra el `.env` que acababa de crear para volver a preguntar.
- **Lo que tiene que dejar listo quien administra PostgreSQL:**
  - que escuche en esa IP (`listen_addresses` en `postgresql.conf`);
  - que `pg_hba.conf` deje entrar a la red de Docker, por ejemplo `host  laboratorio  testlab  172.16.0.0/12  scram-sha-256`, y recargar PostgreSQL;
  - que el usuario sea el dueño de la base (o pueda crear tablas en ella).
- **Seguridad (S.1).** Lo ideal es que la aplicación entre con un usuario que solo lee y escribe filas, distinto del que crea las tablas. Para crearlo, el usuario de la base necesita `CREATEROLE` (`ALTER ROLE usuario CREATEROLE`), y tiene que conservarlo, porque cada actualización lo vuelve a usar. **Sin ese permiso el instalador sigue**, pero la aplicación entra con el mismo usuario que migra (`DB_USUARIO_UNICO=true`) y lo avisa: sirve para un sandbox, es peor para producción.
- **Las copias de seguridad** siguen siendo del servicio `copias`: hacen `pg_dump` por la red y se restauran con las tablas dentro de la misma base, sin borrarla.
- **La contraseña** no puede llevar espacios, `$`, `#`, `\` ni comillas, porque el `.env` los confunde: pídele al ingeniero otra sin ellos.

Sin preguntas: `BASE_EXTERNA_HOST`, `BASE_EXTERNA_PUERTO`, `BASE_EXTERNA_NOMBRE`, `BASE_EXTERNA_USUARIO` y `BASE_EXTERNA_CONTRASENA` (sección 9).

### Después de instalar

```bash
./produccion.sh verificar
```

Revisa que los seis servicios estén arriba, que la plataforma responda, que estén los roles y el ADMIN, y cuándo fue la última copia de seguridad.

**Guarda una copia del `.env` fuera del servidor**, en un lugar seguro distinto del de las copias. Tiene la `APP_KEY`: sin ella no se recupera el documento de identidad de nadie, aunque haya copias de la base.

**Si cambias el `.env`** (llegaron las credenciales de Google, la IP del proxy, el correo), vuelve a correr `./produccion.sh instalar`: no toca lo que ya está y recrea los contenedores para que lo lean.

La portada se verá vacía, con cada bloque en su estado sin foto, hasta que el ADMIN cargue el contenido en `/admin`.

---

## 4. Entrada con Google

Google pone dos condiciones a la dirección de retorno: **tiene que ser `https://` y un nombre, no una IP**. La única excepción es `localhost`. Por eso, con el servidor solo por IP y sin HTTPS, nadie puede entrar todavía; para probarla antes de tener HTTPS está el túnel del final de esta sección.

Las credenciales se crean en <https://console.cloud.google.com/> («APIs y servicios»), idealmente con una cuenta de la universidad, para que el proyecto quede en su organización:

1. **Pantalla de consentimiento de OAuth**, tipo **Interno**: así solo entran cuentas de la universidad.
2. **Credenciales → Crear credenciales → ID de cliente de OAuth**, tipo **Aplicación web**.
3. En **URI de redireccionamiento autorizados**, la dirección de la plataforma seguida de `/acceso/google/volver`, por ejemplo `https://laboratorio.ufps.edu.co/acceso/google/volver`. `./produccion.sh verificar` la imprime mientras falte.
4. Copia el ID y el secreto al `.env`, junto con el dominio:

```ini
GOOGLE_CLIENT_ID=1234567890-abc.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-...
GOOGLE_DOMINIO_INSTITUCIONAL=ufps.edu.co
```

Y vuelve a correr `./produccion.sh instalar`. **Sin `GOOGLE_DOMINIO_INSTITUCIONAL`, en producción no entra nadie**, a propósito: sin él cualquier cuenta de Google pasaría (RF18).

Si Google responde `redirect_uri_mismatch`, la dirección registrada no coincide con la que manda la plataforma. Detrás de un proxy con HTTPS lo normal es que falte `PROXIES_DE_CONFIANZA`: sin él, la plataforma cree que la petición llegó por `http://` y manda esa dirección.

### Probar la entrada sin HTTPS: túnel SSH

Desde tu equipo, abre un túnel al servidor (cambia el 80 si la plataforma usa otro puerto):

```bash
ssh -L 8080:localhost:80 usuario@ip-del-servidor
```

Con el túnel abierto, `http://localhost:8080` en tu navegador es la plataforma del servidor, y Google sí acepta esa dirección. En el `.env` del servidor pon `APP_URL=http://localhost:8080`, registra en Google `http://localhost:8080/acceso/google/volver` y vuelve a correr `./produccion.sh instalar`. Sirve para que una persona pruebe; para que entren todos hace falta el nombre y el HTTPS.

---

## 5. Correo

Los avisos salen de la cuenta de Google del laboratorio por `smtp.gmail.com` (`docs/trazabilidad.md`, «Bloqueos externos»). Esa cuenta necesita la verificación en dos pasos activada y una **contraseña de aplicación** (en la cuenta de Google: Seguridad → Contraseñas de aplicaciones). El script la pregunta en cada pasada mientras falte; también se puede poner a mano:

```ini
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=laboratorio@ufps.edu.co
MAIL_PASSWORD=abcdefghijklmnop
MAIL_FROM_ADDRESS=laboratorio@ufps.edu.co
```

Si alguien cambia la contraseña de la cuenta, Google anula la de aplicación y los correos dejan de salir hasta generar otra. Mientras no haya correo, los avisos se ven con `docker compose -f docker-compose.produccion.yml logs queue`.

---

## 6. Plan B: el servidor no sale a internet

Las imágenes se construyen en un equipo que sí tenga internet y Docker, y se llevan en una memoria USB junto con el código. En tu equipo, desde la carpeta del repositorio, con todo commiteado:

```bash
./produccion.sh empaquetar
```

Deja en `imagenes-docker/` tres archivos: `laboratorio-imagenes-<versión>.tar.gz` (unos cientos de MB), su suma `.sha256` y `laboratorio-codigo-<versión>.bundle`, el código commiteado. Las imágenes se construyen para `linux/amd64`, que es lo normal en un servidor; si fuera otro, `PLATAFORMA=linux/arm64 ./produccion.sh empaquetar`.

En el servidor, con la memoria montada (por ejemplo en `/media/usb`):

```bash
git clone /media/usb/laboratorio-codigo-<versión>.bundle /srv/laboratorio
```

```bash
cd /srv/laboratorio && ./produccion.sh instalar --imagenes /media/usb/laboratorio-imagenes-<versión>.tar.gz
```

El script comprueba la suma antes de cargar nada. Si después el servidor consigue salida a GitHub, se apunta el código a él para que `actualizar` funcione normal:

```bash
git remote set-url origin https://github.com/KevinZorro/practicasProfecionales.git
```

---

## 7. Si la salida a internet pasa por un proxy

Docker necesita saberlo dos veces: para descargar imágenes (el servicio de Docker) y para construir (las órdenes `RUN` del Dockerfile). Con el proxy de ejemplo `http://proxy.ufps.edu.co:3128`:

```bash
sudo mkdir -p /etc/systemd/system/docker.service.d
printf '[Service]\nEnvironment="HTTP_PROXY=http://proxy.ufps.edu.co:3128"\nEnvironment="HTTPS_PROXY=http://proxy.ufps.edu.co:3128"\nEnvironment="NO_PROXY=localhost,127.0.0.1"\n' | sudo tee /etc/systemd/system/docker.service.d/proxy.conf
sudo systemctl daemon-reload && sudo systemctl restart docker
```

Y en `~/.docker/config.json` del usuario que instala (Docker se lo pasa a cada construcción y a cada contenedor):

```json
{
  "proxies": {
    "default": {
      "httpProxy": "http://proxy.ufps.edu.co:3128",
      "httpsProxy": "http://proxy.ufps.edu.co:3128",
      "noProxy": "localhost,127.0.0.1,db,app,web"
    }
  }
}
```

El correo no pasa por un proxy HTTP: para enviar, el servidor necesita llegar directo a `smtp.gmail.com:587`. Si eso no es posible, el plan alterno es el relay de Google Workspace que autoriza sistemas (`docs/trazabilidad.md`).

---

## 8. Actualizar a una versión nueva

```bash
cd /srv/laboratorio && ./produccion.sh actualizar
```

Trae el código con `git pull`, **hace una copia de seguridad antes de migrar**, reconstruye las imágenes, migra y levanta. Sin internet: trae el código del bundle nuevo y carga sus imágenes.

```bash
git pull /media/usb/laboratorio-codigo-<versión>.bundle
```

```bash
./produccion.sh actualizar --sin-git --imagenes /media/usb/laboratorio-imagenes-<versión>.tar.gz
```

Otro ADMIN, o uno nuevo si el último se fue: `./produccion.sh administrador correo@ufps.edu.co "Nombre Apellido"`. Si la cuenta ya existe, basta el correo.

---

## 9. Sin preguntas

Sin una terminal, o con `NO_INTERACTIVO=1`, el script no pregunta nada y toma las respuestas de variables de entorno. Así lo corre la CI. Solo llenan lo que falta en el `.env`: si ya existe, sus valores mandan. `APP_PORT` y `DIRECTORIO_COPIAS` se toman solo al crear el `.env`.

| Variable | Para qué |
|---|---|
| `APP_URL` | La dirección. Obligatoria la primera vez. |
| `APP_PORT`, `DIRECTORIO_COPIAS` | El puerto y la carpeta de las copias, si no sirven los del ejemplo. |
| `PROXIES_DE_CONFIANZA` | La IP del proxy que pone el HTTPS. |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_DOMINIO_INSTITUCIONAL` | La entrada con Google. |
| `CORREO_LABORATORIO`, `CORREO_CONTRASENA_DE_APLICACION` | El correo de los avisos. |
| `BASE_EXTERNA_HOST`, `BASE_EXTERNA_PUERTO`, `BASE_EXTERNA_NOMBRE`, `BASE_EXTERNA_USUARIO`, `BASE_EXTERNA_CONTRASENA` | Una base PostgreSQL que ya existe. Sin `BASE_EXTERNA_HOST`, la de la instalación. |
| `ADMIN_CORREO`, `ADMIN_NOMBRE` | El primer ADMIN. |

```bash
NO_INTERACTIVO=1 APP_URL=http://10.0.0.5 ADMIN_CORREO=ana@ufps.edu.co ADMIN_NOMBRE="Ana Pérez" ./produccion.sh instalar
```

Las contraseñas en la línea de órdenes quedan en el historial de la terminal: para ellas, mejor el modo con preguntas.

---

## 10. Si algo sale mal

| Lo que se ve | Qué pasa y qué hacer |
|---|---|
| «Tu usuario no puede usar Docker» | Falta el grupo `docker` (sección 2) o correrlo con `sudo`. |
| «El puerto 80 ya lo usa otro programa» | Con preguntas, el script pide otro. Sin ellas: `APP_PORT=8080 ./produccion.sh instalar`. |
| La construcción falla con `timeout`, `could not resolve` o `TLS` | El servidor no sale a internet o lo hace por un proxy: secciones 6 y 7. |
| «Ya hay una base de datos de una instalación anterior» | Hay datos sin su `.env`. Recupera ese `.env`; si es un sandbox y quieres empezar de cero, la última fila. |
| «No pude entrar a la base» | El mensaje dice la causa: contraseña, `pg_hba.conf` (la red de Docker), puerto o dirección. Ver «La base de datos». |
| `password authentication failed` al migrar | El `.env` no es el de la base que hay en el servidor. Mismo remedio. |
| La plataforma no responde después de levantar | `docker compose -f docker-compose.produccion.yml logs --tail=50 app web`. |
| «419 Page Expired» al entrar | La cookie segura sin HTTPS. Vuelve a correr `./produccion.sh instalar`, que la ajusta a `APP_URL`. |
| Google dice `redirect_uri_mismatch` | Sección 4: la dirección registrada, `APP_URL` y `PROXIES_DE_CONFIANZA`. |
| Entra a Google pero la plataforma dice que no puede entrar | No tiene cuenta, o no es del dominio. Créala con `./produccion.sh administrador` o desde `/panel/usuarios`. |

**Empezar de cero en el sandbox.** Borra la base, las fotos y los documentos subidos, y con ellos todo lo que se probó. Nunca en un servidor con datos reales:

```bash
docker compose -f docker-compose.produccion.yml down -v && rm .env
```
