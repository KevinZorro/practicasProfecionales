#!/usr/bin/env bash
# Instala y mantiene la plataforma SIN Docker (ADR 0010): PHP-FPM, nginx y
# Composer del propio servidor, y PostgreSQL en otra máquina. No usa sudo:
# lo corre el usuario que despliega, dueño de la carpeta del código.
#
#   ./nativo.sh configurar      crea el .env desde .env.nativo.example y genera APP_KEY
#   ./nativo.sh instalar        primera vez: dependencias, assets, base, caché y cron
#   ./nativo.sh actualizar      versión nueva: git pull, copia, dependencias, migraciones y caché
#   ./nativo.sh verificar       revisa PHP, extensiones, base, permisos y que responda
#   ./nativo.sh cron            instala en el crontab del usuario la cola y el programador
#   ./nativo.sh copia           copia de seguridad de la base y los archivos subidos
#   ./nativo.sh administrador CORREO [NOMBRE]   nombra un ADMIN
#
# La guía, con lo que le toca al administrador del servidor, está en
# docs/despliegue-nativo.md. Cada orden que cambia algo se imprime antes
# con «$»: así se ve qué hace y se puede repetir a mano.

set -euo pipefail

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$RAIZ"

ARCHIVO_ENV="$RAIZ/.env"
EJEMPLO="$RAIZ/.env.nativo.example"

# El PHP con el que corre todo: el mismo que usa PHP-FPM. Si el servidor
# tiene varios, php8.3 manda sobre el "php" por defecto.
PHP="${PHP:-$(command -v php8.3 || command -v php || true)}"
PHP_MINIMO="8.3.0"
# El parche de PHP2Shell (ConfiguracionDeProduccionTest fija la imagen en él).
PHP_PARCHEADO="8.3.36"
EXTENSIONES=(pdo_pgsql gd intl zip mbstring xml dom curl fileinfo openssl tokenizer ctype)

# Marca del bloque que este script escribe en el crontab, para reemplazarlo
# sin tocar el resto.
MARCA_CRON="# laboratorio (nativo.sh)"

SIN_GIT=""

if [ -t 1 ]; then
    NEGRITA=$'\033[1m'; ROJO=$'\033[31m'; AMARILLO=$'\033[33m'; VERDE=$'\033[32m'; NORMAL=$'\033[0m'
else
    NEGRITA=""; ROJO=""; AMARILLO=""; VERDE=""; NORMAL=""
fi

paso()   { printf '\n%s==> %s%s\n' "$NEGRITA" "$*" "$NORMAL"; }
bien()   { printf '    %s[ok]%s %s\n' "$VERDE" "$NORMAL" "$*"; }
aviso()  { printf '    %s[aviso]%s %s\n' "$AMARILLO" "$NORMAL" "$*"; }
mal()    { printf '    %s[mal]%s %s\n' "$ROJO" "$NORMAL" "$*"; FALLAS=$((FALLAS + 1)); }
fallar() { printf '\n%s[error]%s %s\n' "$ROJO" "$NORMAL" "$*" >&2; exit 1; }
FALLAS=0

ejecutar() {  # imprime la orden y la corre
    printf '    $ %s\n' "$*"
    "$@"
}

artisan() { ejecutar "$PHP" artisan "$@"; }

# ---------------------------------------------------------------------------
# El .env
# ---------------------------------------------------------------------------

valor() {  # valor CLAVE: lo que dice el .env, sin comillas
    [ -f "$ARCHIVO_ENV" ] || return 0
    CLAVE="$1" awk -v comilla="'" '
        index($0, ENVIRON["CLAVE"] "=") == 1 { v = substr($0, length(ENVIRON["CLAVE"]) + 2) }
        END {
            sub(/\r$/, "", v)
            if (v ~ /^".*"$/ || v ~ ("^" comilla ".*" comilla "$")) v = substr(v, 2, length(v) - 2)
            print v
        }
    ' "$ARCHIVO_ENV"
}

poner() {  # poner CLAVE VALOR: cambia la línea CLAVE= del .env
    local temporal
    temporal="$(mktemp "$ARCHIVO_ENV.XXXXXX")"
    CLAVE="$1" VALOR="$2" awk '
        index($0, ENVIRON["CLAVE"] "=") == 1 { print ENVIRON["CLAVE"] "=" ENVIRON["VALOR"]; hecho = 1; next }
        { print }
        END { if (!hecho) print ENVIRON["CLAVE"] "=" ENVIRON["VALOR"] }
    ' "$ARCHIVO_ENV" > "$temporal"
    chmod --reference="$ARCHIVO_ENV" "$temporal"
    mv "$temporal" "$ARCHIVO_ENV"
}

es_marca() { case "$1" in \<*) return 0 ;; *) return 1 ;; esac; }

marcas_pendientes() {  # las claves que siguen con <...>
    grep -E '^[A-Z_]+=<' "$ARCHIVO_ENV" | cut -d= -f1 || true
}

hay_dueno() { [ -n "$(valor DB_DUENO_USUARIO)" ]; }

# ---------------------------------------------------------------------------
# Permisos: PHP-FPM corre como www-data y el código es del usuario que
# despliega. Si ese usuario está en el grupo www-data, se comparte por grupo;
# si no (sandbox sin sudo), storage y bootstrap/cache quedan abiertos a todos.
# ---------------------------------------------------------------------------

en_grupo_web() { [[ " $(id -nG) " == *" www-data "* ]]; }

ajustar_permisos() {
    paso "Permisos de storage y bootstrap/cache"
    if en_grupo_web; then
        ejecutar chgrp -R www-data storage bootstrap/cache
        ejecutar chmod -R g+rwX storage bootstrap/cache
        ejecutar find storage bootstrap/cache -type d -exec chmod g+s {} +
        # El .env y la configuración en caché tienen las contraseñas: solo el
        # dueño y PHP-FPM los leen.
        ejecutar chgrp www-data "$ARCHIVO_ENV"
        ejecutar chmod 640 "$ARCHIVO_ENV"
        bien "Compartidos con el grupo www-data"
    else
        ejecutar chmod -R o+rwX storage bootstrap/cache
        aviso "$(id -un) no está en el grupo www-data: storage y bootstrap/cache quedan escribibles por todos"
        aviso "y la configuración en caché (con las contraseñas) legible por todos. Ver docs/despliegue-nativo.md."
    fi
}

# Los topes de subida de los videos (32M y 36M, ConfiguracionLandingTest) y
# la mitigación de PHP2Shell, sin tocar el php.ini del servidor: PHP-FPM lee
# public/.user.ini. No se versiona: es del servidor.
escribir_user_ini() {
    local destino="$RAIZ/public/.user.ini"
    [ -f "$destino" ] && return 0
    cat > "$destino" <<'INI'
; Lo escribe nativo.sh. Los mismos valores que docker/php/php-produccion.ini.
upload_max_filesize = 32M
post_max_size = 36M
memory_limit = 512M
max_execution_time = 120
session.upload_progress.enabled = 0
display_errors = Off
INI
    bien "Escribió public/.user.ini (topes de subida para PHP-FPM)"
}

# ---------------------------------------------------------------------------
# Órdenes
# ---------------------------------------------------------------------------

configurar() {
    paso "Archivo .env"
    if [ -f "$ARCHIVO_ENV" ]; then
        bien "Ya existe: no se cambian sus valores"
    else
        ejecutar cp "$EJEMPLO" "$ARCHIVO_ENV"
        chmod 600 "$ARCHIVO_ENV"
    fi

    if es_marca "$(valor APP_KEY)" || [ -z "$(valor APP_KEY)" ]; then
        poner APP_KEY "base64:$(head -c 32 /dev/urandom | base64 | tr -d '\n')"
        bien "Generó APP_KEY. Guárdala aparte: sin ella se pierden los documentos cifrados."
    fi

    local pendientes
    pendientes="$(marcas_pendientes)"
    if [ -n "$pendientes" ]; then
        aviso "Faltan por llenar en .env: $(echo "$pendientes" | tr '\n' ' ')"
        return 1
    fi
    bien "Sin valores por llenar"
}

requisitos() {
    paso "Requisitos"
    [ -n "$PHP" ] || fallar "No se encontró PHP. Lo instala el administrador del servidor (php8.3-fpm y php8.3-cli)."

    local version
    version="$("$PHP" -r 'echo PHP_VERSION;')"
    if "$PHP" -r "exit(version_compare(PHP_VERSION, '$PHP_MINIMO', '>=') ? 0 : 1);"; then
        bien "PHP $version ($PHP)"
    else
        mal "PHP $version: hace falta $PHP_MINIMO o superior"
    fi
    "$PHP" -r "exit(version_compare(PHP_VERSION, '$PHP_PARCHEADO', '>=') ? 0 : 1);" ||
        aviso "PHP $version no trae el parche de PHP2Shell ($PHP_PARCHEADO). public/.user.ini apaga upload_progress, que lo mitiga."
    "$PHP" -r "exit(version_compare(PHP_VERSION, '8.4.0', '<') ? 0 : 1);" ||
        aviso "PHP $version: el proyecto está probado en 8.3 (config.platform.php de composer.json)."

    local faltan=()
    for extension in "${EXTENSIONES[@]}"; do
        "$PHP" -m | grep -qix "$extension" || faltan+=("$extension")
    done
    if [ ${#faltan[@]} -eq 0 ]; then
        bien "Extensiones: ${EXTENSIONES[*]}"
    else
        mal "Faltan extensiones de PHP: ${faltan[*]} (paquetes php8.3-<nombre>)"
    fi
    if "$PHP" -r 'exit(function_exists("imagewebp") ? 0 : 1);'; then
        bien "GD guarda WebP"
    else
        mal "GD sin WebP: las imágenes públicas se guardan en WebP (RNF10)"
    fi
    "$PHP" -m | grep -qix exif || aviso "Sin exif: las fotos del celular no se enderezan solas"
    "$PHP" -m | grep -qix pcntl || aviso "Sin pcntl: el --timeout de la cola no corta un trabajo colgado"

    command -v composer >/dev/null && bien "Composer" || mal "Falta Composer"
    command -v npm >/dev/null && bien "Node $(node -v 2>/dev/null) y npm" || mal "Falta npm (compila los assets con Vite)"
    command -v flock >/dev/null || aviso "Sin flock: el cron no puede evitar dos trabajadores de la cola a la vez"

    [ "$FALLAS" -eq 0 ] || fallar "Faltan requisitos. Se los pide al administrador del servidor (docs/despliegue-nativo.md)."
}

base_responde() {
    paso "Base de datos"
    # Con PDO directo y no con artisan: así responde aunque la base esté vacía
    # o la configuración en caché sea vieja.
    if DB_HOST="$(valor DB_HOST)" DB_PORT="$(valor DB_PORT)" DB_DATABASE="$(valor DB_DATABASE)" \
        DB_USERNAME="$(valor DB_USERNAME)" DB_PASSWORD="$(valor DB_PASSWORD)" "$PHP" -r '
            new PDO(sprintf("pgsql:host=%s;port=%s;dbname=%s;connect_timeout=5", getenv("DB_HOST"), getenv("DB_PORT"), getenv("DB_DATABASE")),
                getenv("DB_USERNAME"), getenv("DB_PASSWORD"));' 2>/dev/null; then
        bien "Conecta con $(valor DB_HOST):$(valor DB_PORT)/$(valor DB_DATABASE) como $(valor DB_USERNAME)"
        return 0
    fi
    mal "No conecta con $(valor DB_HOST):$(valor DB_PORT)/$(valor DB_DATABASE) como $(valor DB_USERNAME)"
    echo "    Pruébalo a mano: psql -h $(valor DB_HOST) -p $(valor DB_PORT) -U $(valor DB_USERNAME) -d $(valor DB_DATABASE)"
    return 1
}

dependencias() {
    paso "Dependencias de PHP"
    ejecutar composer install --no-dev --optimize-autoloader --no-interaction --no-progress

    paso "Assets (Vite)"
    ejecutar npm ci --no-audit --no-fund
    ejecutar npm run build
}

migrar() {
    paso "Migraciones y roles"
    # La configuración en caché ignoraría las variables de abajo.
    artisan config:clear
    if hay_dueno; then
        # Las variables del entorno mandan sobre el .env: las tablas las crea el
        # dueño y la aplicación queda con su usuario de solo filas (S.1), como
        # el servicio "migraciones" de docker-compose.produccion.yml.
        printf '    (con el dueño de la base, %s)\n' "$(valor DB_DUENO_USUARIO)"
        DB_USERNAME="$(valor DB_DUENO_USUARIO)" DB_PASSWORD="$(valor DB_DUENO_CONTRASENA)" \
        DB_APLICACION_USUARIO="$(valor DB_USERNAME)" DB_APLICACION_CONTRASENA="$(valor DB_PASSWORD)" \
            bash -c '"$0" artisan migrate --force && "$0" artisan db:seed --class=RolSeeder --force && "$0" artisan base:usuario-de-la-aplicacion' "$PHP"
    else
        artisan migrate --force
        artisan db:seed --class=RolSeeder --force
    fi
}

cachear() {
    paso "Caché de configuración, rutas, eventos y vistas"
    [ -L public/storage ] || artisan storage:link
    artisan optimize
    if en_grupo_web; then
        chgrp www-data bootstrap/cache/config.php && chmod 640 bootstrap/cache/config.php
    fi
    # Los trabajadores de la cola cargan el código una vez: que tomen el nuevo.
    artisan queue:restart
}

copia() (  # en un subshell: el umask 077 no alcanza a lo que sigue
    local directorio retencion marca destino en_curso usuario contrasena
    directorio="$(valor DIRECTORIO_COPIAS)"
    [ -n "$directorio" ] || fallar "DIRECTORIO_COPIAS está vacío en .env: no hay dónde guardar la copia."
    retencion="$(valor COPIAS_RETENCION_DIAS)"
    command -v pg_dump >/dev/null || fallar "Falta pg_dump (paquete postgresql-client)."

    if hay_dueno; then
        usuario="$(valor DB_DUENO_USUARIO)"; contrasena="$(valor DB_DUENO_CONTRASENA)"
    else
        usuario="$(valor DB_USERNAME)"; contrasena="$(valor DB_PASSWORD)"
    fi

    mkdir -p "$RAIZ/storage/app/public" "$RAIZ/storage/app/private"
    # Tienen datos personales: solo las lee el usuario que despliega.
    umask 077
    mkdir -p "$directorio"
    while [ -e "$directorio/$(date +%Y-%m-%d_%H%M)" ]; do sleep 5; done
    marca="$(date +%Y-%m-%d_%H%M)"
    destino="$directorio/$marca"
    en_curso="$directorio/.en-curso-$marca"

    # El mismo formato que docker/produccion/copias/hacer-copia.sh: se
    # restaura igual (docs/despliegue-nativo.md).
    rm -rf "$en_curso"; mkdir -p "$en_curso"
    PGPASSWORD="$contrasena" pg_dump --format=custom --no-owner \
        --host="$(valor DB_HOST)" --port="$(valor DB_PORT)" --username="$usuario" \
        --dbname="$(valor DB_DATABASE)" --file="$en_curso/base-de-datos.dump"
    tar -czf "$en_curso/archivos-publicos.tar.gz" -C "$RAIZ/storage/app/public" .
    tar -czf "$en_curso/archivos-privados.tar.gz" -C "$RAIZ/storage/app/private" .
    (cd "$en_curso" && sha256sum base-de-datos.dump archivos-publicos.tar.gz archivos-privados.tar.gz > SHA256SUMS)
    mv "$en_curso" "$destino"

    find "$directorio" -mindepth 1 -maxdepth 1 -type d -name '20??-??-??_????' -mtime +"${retencion:-14}" -exec rm -rf {} +
    find "$directorio" -mindepth 1 -maxdepth 1 -type d -name '.en-curso-*' -mtime +1 -exec rm -rf {} +
    echo "Copia terminada: $destino ($(du -sh "$destino" | cut -f1))"
)

lineas_cron() {
    # La cola: un trabajador a la vez (flock). --max-time lo cierra cada hora
    # para que no acumule memoria, y el cron lo vuelve a levantar en menos de
    # un minuto; igual si se cae.
    echo "* * * * * cd $RAIZ && flock -n storage/framework/cola.lock $PHP artisan queue:work --tries=3 --timeout=90 --max-time=3600 >/dev/null 2>&1"
    # Las tareas programadas (routes/console.php): el aviso del RF60.
    echo "* * * * * cd $RAIZ && $PHP artisan schedule:run >/dev/null 2>&1"
    if [ -n "$(valor DIRECTORIO_COPIAS)" ]; then
        echo "30 2 * * * $RAIZ/nativo.sh copia >>$RAIZ/storage/logs/copias.log 2>&1"
    fi
}

cron() {
    paso "Cron de $(id -un): cola, programador y copias"
    if ! command -v crontab >/dev/null; then
        mal "No hay crontab: sin él no salen correos ni corren las tareas programadas."
        echo "    Pídele al administrador que deje usarlo, o que cree los servicios de docs/despliegue-nativo.md."
        return 1
    fi
    local actual nuevo
    actual="$(crontab -l 2>/dev/null || true)"
    # Quita el bloque anterior (de la marca a la línea en blanco) y pone el nuevo.
    nuevo="$(printf '%s\n' "$actual" | awk -v marca="$MARCA_CRON" '
        $0 == marca { saltar = 1; next }
        saltar && $0 == "" { saltar = 0; next }
        !saltar { print }
    ')"
    nuevo="$(printf '%s\n%s\n%s\n' "$nuevo" "$MARCA_CRON" "$(lineas_cron)")"
    printf '%s\n\n' "$nuevo" | sed '/./,$!d' | crontab -
    lineas_cron | sed 's/^/    /'
    bien "Crontab al día (crontab -l para verlo)"
}

verificar() {
    requisitos
    [ -f "$ARCHIVO_ENV" ] || fallar "No hay .env. Corre ./nativo.sh configurar"
    [ -z "$(marcas_pendientes)" ] || mal "Faltan por llenar en .env: $(marcas_pendientes | tr '\n' ' ')"
    [ "$(valor APP_DEBUG)" = "false" ] || mal "APP_DEBUG no es false: mostraría trazas con datos"
    base_responde || true

    paso "Aplicación"
    [ -f vendor/autoload.php ] && bien "Dependencias instaladas" || mal "Sin vendor/: corre ./nativo.sh instalar"
    [ -f public/build/manifest.json ] && bien "Assets compilados" || mal "Sin public/build: corre ./nativo.sh instalar"
    [ -f bootstrap/cache/config.php ] && bien "Configuración en caché" ||
        mal "Sin configuración en caché: PHP-FPM tendría que leer el .env. Corre ./nativo.sh actualizar --sin-git"
    [ -L public/storage ] && bien "Enlace public/storage" || mal "Falta public/storage: php artisan storage:link"
    [ -f storage/framework/down ] && aviso "En mantenimiento: php artisan up para abrirla"
    if [ -w storage/logs ] && [ -w bootstrap/cache ]; then bien "storage y bootstrap/cache escribibles"; else mal "storage o bootstrap/cache no se pueden escribir"; fi
    crontab -l 2>/dev/null | grep -qF "$MARCA_CRON" && bien "Cron instalado" || aviso "Sin cron: no salen correos ni corren las tareas programadas (./nativo.sh cron)"

    local url codigo
    url="$(valor APP_URL)"
    if command -v curl >/dev/null && ! es_marca "$url"; then
        codigo="$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "$url/up" || true)"
        [ "$codigo" = "200" ] && bien "$url/up responde 200" || mal "$url/up responde ${codigo:-nada} (¿nginx apunta a $RAIZ/public?)"
    fi

    [ "$FALLAS" -eq 0 ] || fallar "$FALLAS problema(s)."
    paso "Todo en orden"
}

instalar() {
    configurar || fallar "Llena esos valores en .env y vuelve a correr ./nativo.sh instalar"
    requisitos
    escribir_user_ini
    dependencias
    base_responde || fallar "Sin base no se puede seguir."
    migrar
    cachear
    ajustar_permisos
    cron || true
    paso "Instalada"
    echo "    Falta el primer ADMIN: ./nativo.sh administrador correo@dominio.edu.co \"Nombre Apellido\""
    echo "    Y revisarla: ./nativo.sh verificar"
}

actualizar() {
    [ -f "$ARCHIVO_ENV" ] || fallar "No hay .env: esto es una instalación nueva. Corre ./nativo.sh instalar"
    requisitos

    if [ -z "$SIN_GIT" ]; then
        paso "Código"
        ejecutar git pull --ff-only ||
            fallar "git pull no pudo traer la versión nueva. ¿Hay cambios a mano? Míralos con git status. Para seguir con el código tal como está: --sin-git"
        bien "Código en $(git log -1 --format='%h %s')"
    fi

    if [ -n "$(valor DIRECTORIO_COPIAS)" ]; then
        paso "Copia de seguridad antes de migrar"
        copia
    fi

    # Mientras cambian vendor/ y la base, quien entra ve "en mantenimiento" y
    # no un error. Si algo falla, se queda así a propósito.
    [ -f vendor/autoload.php ] && artisan down --retry=30
    escribir_user_ini
    dependencias
    migrar
    cachear
    ajustar_permisos
    artisan up
    paso "Actualizada"
}

administrador() {
    [ $# -ge 1 ] || fallar "Uso: ./nativo.sh administrador correo@dominio.edu.co [\"Nombre Apellido\"]"
    artisan usuarios:crear-administrador "$@"
}

ayuda() { sed -n '2,16p' "$0" | sed 's/^# \{0,1\}//'; }

principal() {
    local orden="${1:-ayuda}"
    shift || true
    for argumento in "$@"; do
        [ "$argumento" = "--sin-git" ] && SIN_GIT=1
    done
    case "$orden" in
        configurar) configurar ;;
        instalar) instalar ;;
        actualizar) actualizar ;;
        verificar) verificar ;;
        cron) cron ;;
        copia) copia ;;
        administrador) administrador "$@" ;;
        ayuda | -h | --help) ayuda ;;
        *) ayuda; exit 2 ;;
    esac
}

principal "$@"
