# Plataforma del Laboratorio de Simulación Clínica

Sistema de gestión del Laboratorio de Simulación Clínica de la Facultad de
Ciencias de la Salud: reserva de escenarios clínicos, evaluación de habilidades
e inventario de simuladores.

Todo el entorno corre en Docker. **No necesitas PHP, Composer ni Node instalados
en tu máquina.**

> El `docker-compose.yml` de este repositorio es el **entorno de desarrollo**:
> monta el código desde el host y deja Vite en modo recargado en caliente. El
> despliegue en el servidor institucional se define aparte.

---

## Stack

| Capa | Tecnología |
|---|---|
| Lenguaje | PHP 8.3 |
| Framework | Laravel 12 LTS |
| Vistas | Blade + Livewire 3 |
| Estilos | Tailwind CSS 3 |
| Base de datos | PostgreSQL 16 |
| Permisos | spatie/laravel-permission 7 |
| Autenticación | Laravel Breeze (instalado, sin publicar todavía) |
| Tests | Pest 3 |
| Assets | Vite |

---

## Requisitos

- Docker Engine 24 o superior
- Docker Compose v2 o superior (`docker compose`, sin guion)

Comprobación rápida:

```bash
docker --version
docker compose version
```

---

## Levantar el proyecto por primera vez

Desde la raíz del repositorio, en este orden:

```bash
# 1. Copiar la configuración de ejemplo
cp .env.example .env

# 2. Ajustar UID y GID al usuario del host para que los archivos que genere
#    artisan no queden con otro propietario. En Linux y macOS:
sed -i "s/^UID=.*/UID=$(id -u)/;s/^GID=.*/GID=$(id -g)/" .env

# 3. Construir la imagen de PHP (la primera vez tarda varios minutos)
docker compose build

# 4. Levantar los seis servicios: app, queue, programador, nginx, db y node
docker compose up -d

# 5. Instalar las dependencias de PHP
docker compose exec app composer install

# 6. Generar la clave de la aplicación
docker compose exec app php artisan key:generate

# 7. Crear las tablas
docker compose exec app php artisan migrate
```

Listo. La aplicación queda en **http://localhost**.

El servicio `node` instala las dependencias de JavaScript y arranca Vite solo,
así que no hace falta ejecutar `npm install` a mano.

El servicio `queue` procesa la cola: es el que envía los correos (por ejemplo,
el aviso al docente cuando se aprueba su solicitud). Espera a que exista
`vendor/` y, hasta que se crean las tablas en el paso 7, se reinicia solo; es
normal. Si un correo no llega, lo primero es `docker compose logs queue`.

El servicio `programador` corre las tareas programadas de `routes/console.php`
(`schedule:work`). Hoy es una: el aviso diario, a las 6 de la mañana, de las
sesiones próximas sin formato intramural (RF60). Para lanzarlo a mano:
`docker compose exec app php artisan sesiones:avisar-formato-intramural`.

---

## Verificar que quedó bien

```bash
# Los cinco servicios en estado "Up"
docker compose ps

# Debe mostrar "Laravel Framework 12.x"
docker compose exec app php artisan --version

# La suite de pruebas debe pasar en verde
docker compose exec app php artisan test

# La página de bienvenida debe responder 200
curl -I http://localhost
```

---

## Servicios

| Servicio | Imagen | Puerto en el host | Para qué sirve |
|---|---|---|---|
| `app` | build de `docker/php/Dockerfile` | — | PHP 8.3-FPM: ejecuta la aplicación, artisan, composer y los tests |
| `nginx` | `nginx:alpine` | `80` | Servidor web; entrega los estáticos y delega el PHP a `app` |
| `db` | `postgres:16` | `5432` | Base de datos, con volumen persistente `datos_postgres` |
| `node` | `node:22-alpine` | `5173` | Compila los assets con Vite y sirve el recargado en caliente |

Los puertos se cambian en el `.env` con `APP_PORT`, `DB_PORT_HOST` y `VITE_PORT`
(útil si el 80 o el 5432 ya están ocupados en tu máquina).

---

## Comandos del día a día

```bash
# Arrancar y detener
docker compose up -d
docker compose down

# Ver los logs
docker compose logs -f app
docker compose logs -f nginx

# Artisan
docker compose exec app php artisan migrate
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan make:model Solicitud -mf
docker compose exec app php artisan tinker

# Tests
docker compose exec app php artisan test
docker compose exec app php artisan test --filter=Solicitud

# Estilo de código
docker compose exec app ./vendor/bin/pint

# Composer
docker compose exec app composer install
docker compose exec app composer require proveedor/paquete

# Assets
docker compose exec node npm run build      # compilar para producción
docker compose logs -f node                 # ver el servidor de desarrollo de Vite

# Base de datos, con psql
docker compose exec db psql -U laboratorio -d laboratorio_simulacion
```

---

## Base de datos

| | Base | Cuándo se usa |
|---|---|---|
| Desarrollo | `laboratorio_simulacion` | Al navegar por la aplicación |
| Pruebas | `laboratorio_simulacion_testing` | `php artisan test` (definida en `phpunit.xml`) |

La base de pruebas la crea automáticamente
`docker/postgres/init/01-base-de-datos-de-pruebas.sql` la primera vez que se
crea el volumen. Los tests de `Feature` la migran de cero en cada prueba, así
que nunca tocan los datos de desarrollo.

Para empezar de cero, borrando también el volumen:

```bash
docker compose down -v
docker compose up -d
```

---

## Estructura del entorno Docker

```
docker/
├── nginx/
│   └── default.conf                     configuración del sitio (desarrollo)
├── php/
│   ├── Dockerfile                       imágenes de desarrollo y de producción
│   ├── php.ini                          PHP de desarrollo
│   ├── php-produccion.ini               PHP de producción
│   └── www.conf                         pool de PHP-FPM
├── produccion/
│   ├── arrancar.sh                      caché de configuración al arrancar
│   ├── nginx.conf                       nginx de producción
│   └── copias/                          copias de seguridad y restauración
└── postgres/
    └── init/
        └── 01-base-de-datos-de-pruebas.sql
docker-compose.yml                       desarrollo
docker-compose.produccion.yml            producción
```

---

## Producción

El servidor institucional corre la aplicación con `docker-compose.produccion.yml`.
El código, las dependencias y los assets compilados van **dentro de las
imágenes**: en el servidor no hace falta PHP, Composer ni Node, solo Docker.

### Primera instalación

```bash
# 1. El código, en la versión que se va a desplegar
git clone <repositorio> /srv/laboratorio && cd /srv/laboratorio

# 2. La configuración: copiar el ejemplo y llenar las marcas <...>
cp .env.produccion.example .env
chmod 600 .env

# 3. Construir las imágenes y generar la clave (se pega en APP_KEY del .env)
docker compose -f docker-compose.produccion.yml build
docker compose -f docker-compose.produccion.yml run --rm --entrypoint php app artisan key:generate --show

# 4. La carpeta de las copias, solo para root
sudo install -d -m 700 /srv/laboratorio/copias

# 5. Levantar y crear las tablas
docker compose -f docker-compose.produccion.yml up -d
docker compose -f docker-compose.produccion.yml exec app php artisan migrate --force
```

### Desplegar una versión nueva

```bash
git pull
docker compose -f docker-compose.produccion.yml build
docker compose -f docker-compose.produccion.yml up -d
docker compose -f docker-compose.produccion.yml exec app php artisan migrate --force
```

Después de cambiar el `.env`, los contenedores tienen que volver a leerlo:
`docker compose -f docker-compose.produccion.yml up -d --force-recreate app queue programador`.

### HTTPS

El nginx del compose escucha por HTTP en `APP_PORT`. El HTTPS lo termina quien
esté delante: normalmente el proxy de la universidad. En ese caso su IP va en
`PROXIES_DE_CONFIANZA` del `.env`, para que la aplicación sepa que la petición
original era segura (enlaces `https://`, cookie segura y HSTS). `APP_URL`
siempre con `https://`: de ella sale la dirección de retorno que se registra
en Google.

### Cabeceras de seguridad

Las pone el middleware `CabecerasDeSeguridad` en todas las respuestas:
Content-Security-Policy, X-Frame-Options, X-Content-Type-Options,
Referrer-Policy, Permissions-Policy y, sobre HTTPS, HSTS. Si la
Content-Security-Policy rompiera alguna pantalla, se apaga con
`POLITICA_DE_CONTENIDO=false` en el `.env` mientras se corrige
(`config/seguridad.php` explica cada directiva).

### Copias de seguridad

El servicio `copias` hace cada día, a `COPIAS_HORA`:`COPIAS_MINUTO`, una copia
de la base de datos, las imágenes del contenido público y los documentos
privados (el formato de confidencialidad firmado). Cada copia es una carpeta
con fecha dentro de `DIRECTORIO_COPIAS`, con sus sumas SHA-256, y se borran
las de más de `COPIAS_RETENCION_DIAS` días.

Las copias están en el mismo servidor: protegen de un error, no de que se
dañe el disco. **Hay que llevarlas fuera** con el sistema de copias de la
universidad. Tienen datos personales.

```bash
# Hacer una copia ahora
docker compose -f docker-compose.produccion.yml exec copias /scripts/hacer-copia.sh

# Ver que la copia diaria corre
docker compose -f docker-compose.produccion.yml logs copias

# Restaurar una copia (borra lo que hay ahora)
docker compose -f docker-compose.produccion.yml stop app queue programador web
docker compose -f docker-compose.produccion.yml run --rm restauracion 2026-10-01_0230
docker compose -f docker-compose.produccion.yml start app queue programador web
```

La restauración comprueba las sumas antes de borrar nada: una copia dañada no
se restaura. La CI hace una copia, daña los datos y la restaura en cada PR.

---

## Problemas frecuentes

**El puerto 80 está ocupado.** Cambia `APP_PORT` en el `.env` (por ejemplo
`APP_PORT=8080`) y vuelve a levantar con `docker compose up -d`.

**Los archivos que genera artisan quedan con otro propietario.** El `UID` o el
`GID` del `.env` no coinciden con los de tu usuario. Corrígelos con el comando
del paso 2 y reconstruye la imagen: `docker compose build app && docker compose up -d`.

**"Permission denied" al escribir en `storage/`.** Mismo origen que el anterior.
Como arreglo puntual:
`docker compose exec -u root app chown -R www-data:www-data storage bootstrap/cache`.

**La página muestra un error 500 recién levantado.** Falta el paso 5 o el 6:
`composer install` y `key:generate`.

**Los cambios de CSS o JS no se reflejan.** Revisa que el servicio `node` esté
arriba con `docker compose logs -f node`.

---

## Documentación del proyecto

`CLAUDE.md`, en la raíz, contiene el contexto del dominio, los roles, las reglas
de negocio y las convenciones de código. **Léelo antes de escribir código.**
