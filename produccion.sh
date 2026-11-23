#!/usr/bin/env bash
# Instala y mantiene la plataforma en el servidor, con docker-compose.produccion.yml.
#
#   ./produccion.sh instalar        de cero a funcionando: .env, imágenes, base y primer ADMIN
#   ./produccion.sh actualizar      versión nueva: código, copia de seguridad, imágenes y migraciones
#   ./produccion.sh verificar       revisa que todo esté arriba y respondiendo
#   ./produccion.sh administrador   nombra un ADMIN: correo y, si la cuenta no existe, nombre
#   ./produccion.sh configurar      solo prepara el .env, sin tocar Docker
#   ./produccion.sh empaquetar      plan B sin internet: imágenes y código en archivos para llevar
#
# La guía, con lo que hay que pedirle al ingeniero antes de ir, está en
# docs/instalacion-en-el-servidor.md. Cada orden de Docker que corre la
# imprime antes con «$»: así se ve qué hace y se puede repetir a mano.
#
# Se puede correr otra vez sin miedo: no cambia las claves ni las contraseñas
# de un .env que ya existe, y lo que ya está hecho lo deja como está.

set -euo pipefail

# Las rutas que se dan en la orden son desde donde se corrió, no desde la raíz.
DESDE="$PWD"
RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$RAIZ"

COMPOSE_ARCHIVO="docker-compose.produccion.yml"
EJEMPLO="$RAIZ/.env.produccion.example"
# Otro destino solo lo usan las pruebas de "configurar": docker compose lee
# siempre el .env de la raíz.
ARCHIVO_ENV="${ARCHIVO_ENV:-$RAIZ/.env}"

# El "name:" del compose de producción y las imágenes que levanta
# (ConfiguracionDeProduccionTest comprueba que coinciden).
PROYECTO="laboratorio"
IMAGEN_APP="laboratorio-simulacion/app:produccion"
IMAGEN_WEB="laboratorio-simulacion/web:produccion"
IMAGEN_BASE="postgres:16"
IMAGEN_COPIAS="postgres:16-alpine"

# Las imágenes que se empaquetan son para el servidor, no para el equipo que
# las construye.
PLATAFORMA="${PLATAFORMA:-linux/amd64}"

DOMINIO_SUGERIDO="ufps.edu.co"
# Remitente mientras no hay correo: los avisos van al registro, pero el
# mensaje necesita una dirección bien escrita. .invalid es un dominio
# reservado que nunca existirá.
REMITENTE_SIN_CORREO="no-responder@laboratorio.invalid"
DISCO_MINIMO_GB=6
DISCO_RECOMENDADO_GB=20
MEMORIA_MINIMA_MB=2000

IMAGENES=""
SIN_GIT=""

if [ -t 1 ]; then
    NEGRITA=$'\033[1m'; ROJO=$'\033[31m'; AMARILLO=$'\033[33m'; VERDE=$'\033[32m'; NORMAL=$'\033[0m'
else
    NEGRITA=""; ROJO=""; AMARILLO=""; VERDE=""; NORMAL=""
fi

# ---------------------------------------------------------------------------
# Mensajes y preguntas
# ---------------------------------------------------------------------------

paso()   { printf '\n%s==> %s%s\n' "$NEGRITA" "$*" "$NORMAL"; }
bien()   { printf '    %s[ok]%s %s\n' "$VERDE" "$NORMAL" "$*"; }
aviso()  { printf '    %s[aviso]%s %s\n' "$AMARILLO" "$NORMAL" "$*"; }
mal()    { printf '    %s[mal]%s %s\n' "$ROJO" "$NORMAL" "$*"; }
nota()   { printf '    %s\n' "$*"; }
fallar() { printf '\n%s[error]%s %s\n' "$ROJO" "$NORMAL" "$*" >&2; exit 1; }

# Sin terminal (la CI) o con NO_INTERACTIVO=1 no se pregunta nada: las
# respuestas llegan en variables de entorno.
interactivo() { [ -z "${NO_INTERACTIVO:-}" ] && [ -t 0 ]; }

preguntar() {  # preguntar VARIABLE "Pregunta" "valor por defecto"
    local respuesta=""
    read -r -p "    $2${3:+ [$3]}: " respuesta || true
    printf -v "$1" '%s' "${respuesta:-$3}"
}

preguntar_oculto() {  # preguntar_oculto VARIABLE "Pregunta": no se ve lo que se escribe
    local respuesta=""
    read -r -s -p "    $2: " respuesta || true
    printf '\n'
    printf -v "$1" '%s' "$respuesta"
}

confirmar() {  # confirmar "Pregunta": sí solo con s o S
    local respuesta=""
    read -r -p "    $1 [s/N]: " respuesta || true
    case "$respuesta" in s* | S*) return 0 ;; *) return 1 ;; esac
}

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

poner() {  # poner CLAVE VALOR: cambia la línea CLAVE= del .env, o la añade al final
    local temporal
    temporal="$(mktemp "$ARCHIVO_ENV.XXXXXX")"
    # Por ENVIRON y no con -v: awk -v interpreta las barras invertidas.
    CLAVE="$1" VALOR="$2" awk '
        index($0, ENVIRON["CLAVE"] "=") == 1 {
            if (!hecho) print ENVIRON["CLAVE"] "=" ENVIRON["VALOR"]
            hecho = 1
            next
        }
        { print }
        END { if (!hecho) print ENVIRON["CLAVE"] "=" ENVIRON["VALOR"] }
    ' "$ARCHIVO_ENV" > "$temporal"
    chmod 600 "$temporal"
    mv "$temporal" "$ARCHIVO_ENV"
}

asegurar() {  # asegurar CLAVE VALOR: lo pone solo si es distinto, y lo dice
    [ "$(valor "$1")" = "$2" ] && return 0
    poner "$1" "$2"
    bien "$1=$2"
}

es_marca() { case "$1" in \<*) return 0 ;; *) return 1 ;; esac; }

# Los valores que no son del ejemplo se pueden llenar desde el entorno o
# preguntando; los de un .env que ya existía, solo si siguen siendo una marca.
hay_que_llenar() {  # hay_que_llenar CLAVE ES_NUEVO
    local actual
    actual="$(valor "$1")"
    [ -n "$2" ] || [ -z "$actual" ] || es_marca "$actual"
}

secreto() { head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n'; }

clave_de_la_aplicacion() { printf 'base64:%s' "$(head -c 32 /dev/urandom | base64 | tr -d '\n')"; }

generar_si_falta() {  # generar_si_falta CLAVE VALOR_NUEVO "qué es": solo si sigue la marca
    es_marca "$(valor "$1")" || return 0
    poner "$1" "$2"
    bien "Generó $3 ($1)"
}

validar() {  # validar VALOR REGEX "mensaje si no cumple"
    [[ "$1" =~ $2 ]] || fallar "$3"
}

es_https() { case "$(valor APP_URL)" in https://*) return 0 ;; *) return 1 ;; esac; }

# ---------------------------------------------------------------------------
# Docker
# ---------------------------------------------------------------------------

# "docker compose" (el plugin) o "docker-compose" (el binario v2 que trae
# Debian 13 en su paquete): lo decide requisitos_basicos.
COMPOSE_ORDEN=(docker compose)

compose() {
    printf '    $ %s\n' "$(citar "${COMPOSE_ORDEN[@]}" -f "$COMPOSE_ARCHIVO" "$@")"
    "${COMPOSE_ORDEN[@]}" -f "$COMPOSE_ARCHIVO" "$@"
}

citar() {  # la orden tal como se escribiría: lo que lleva espacios, entre comillas
    local argumento texto=""
    for argumento in "$@"; do
        case "$argumento" in
            *[[:space:]]*) texto+=" \"$argumento\"" ;;
            *) texto+=" $argumento" ;;
        esac
    done
    printf '%s' "${texto# }"
}

compose_callado() { "${COMPOSE_ORDEN[@]}" -f "$COMPOSE_ARCHIVO" "$@"; }

# Al armar el compose, docker compose prefiere las variables de la terminal a
# las del .env: una DB_USERNAME exportada para usar psql cambiaría con qué
# usuario entra la aplicación a la base. De aquí en adelante solo manda el .env.
solo_el_env_manda() {
    local clave
    while IFS= read -r clave; do
        unset "$clave" 2>/dev/null || true
    done < <(sed -n 's/^\([A-Z_][A-Z0-9_]*\)=.*/\1/p' "$RAIZ/.env")
}

ejecutar() {
    printf '    $ %s\n' "$(citar "$@")"
    "$@"
}

servicio_corriendo() {  # servicio_corriendo SERVICIO
    [ -f "$RAIZ/.env" ] && [ -n "$(compose_callado ps -q --status running "$1" 2>/dev/null)" ]
}

puerto() { local p; p="$(valor APP_PORT)"; printf '%s' "${p:-80}"; }

puerto_ocupado() {  # algo escucha ya en ese puerto de este servidor
    (exec 3<>"/dev/tcp/127.0.0.1/$1") 2>/dev/null
}

codigo_http() {  # codigo_http RUTA: el código con que responde el nginx del compose
    local ruta=$1 linea=""
    if command -v curl >/dev/null 2>&1; then
        # --noproxy: un http_proxy de la universidad no debe meterse en 127.0.0.1.
        curl -s -o /dev/null -w '%{http_code}' --noproxy '*' --max-time 10 "http://127.0.0.1:$(puerto)$ruta" || true
        return 0
    fi
    # Sin curl (un Debian mínimo): HTTP a mano por el /dev/tcp de bash, en una
    # subshell para que la conexión se cierre al salir.
    linea="$(
        {
            exec 3<>"/dev/tcp/127.0.0.1/$(puerto)"
            printf 'GET %s HTTP/1.0\r\nHost: localhost\r\n\r\n' "$ruta" >&3
            IFS= read -r -t 10 linea <&3
            printf '%s' "$linea"
        } 2>/dev/null || true
    )"
    linea="${linea#* }"
    printf '%s' "${linea%% *}"
}

desde_donde_se_corrio() {  # desde_donde_se_corrio RUTA: absoluta y sin «..»
    local ruta=$1
    case "$ruta" in /*) ;; *) ruta="$DESDE/$ruta" ;; esac
    if [ -d "$(dirname "$ruta")" ]; then
        ruta="$(cd "$(dirname "$ruta")" && pwd)/$(basename "$ruta")"
    fi
    printf '%s' "$ruta"
}

suma_sha256() {
    if command -v sha256sum >/dev/null 2>&1; then sha256sum "$@"; else shasum -a 256 "$@"; fi
}

requisitos_basicos() {
    command -v docker >/dev/null 2>&1 ||
        fallar "No está Docker. Instálalo (docs/instalacion-en-el-servidor.md, «Lo que necesita el servidor») y vuelve a correr esto."

    local salida
    if ! salida="$(docker info 2>&1)"; then
        case "$salida" in
            *"ermission denied"*)
                fallar "Tu usuario no puede usar Docker. Agrégalo al grupo docker (sudo usermod -aG docker \$USER, y vuelve a entrar al servidor) o corre esto con sudo." ;;
            *)
                fallar "Docker está instalado pero no responde. ¿Está arrancado? Prueba: sudo systemctl start docker" ;;
        esac
    fi

    local version
    version="$(docker compose version --short 2>/dev/null || true)"
    if [ -z "$version" ] && command -v docker-compose >/dev/null 2>&1; then
        version="$(docker-compose version --short 2>/dev/null || true)"
        COMPOSE_ORDEN=(docker-compose)
    fi
    [ -n "$version" ] ||
        fallar "Falta Docker Compose v2. Mira «Lo que necesita el servidor» en docs/instalacion-en-el-servidor.md."
    case "${version#v}" in
        1.*) fallar "Docker Compose $version es muy viejo: hace falta la versión 2 o superior." ;;
    esac
    bien "Docker $(docker version --format '{{.Server.Version}}' 2>/dev/null) y Compose ${version#v}"
}

requisitos() {
    paso "Requisitos"
    requisitos_basicos
    comprobar_disco
    comprobar_memoria
}

comprobar_disco() {
    local carpeta libres
    carpeta="$(docker info --format '{{.DockerRootDir}}' 2>/dev/null || true)"
    libres="$(df -Pk "${carpeta:-/}" 2>/dev/null | awk 'NR == 2 { printf "%d", $4 / 1048576 }' || true)"
    [ -n "$libres" ] || return 0

    if [ "$libres" -lt "$DISCO_MINIMO_GB" ]; then
        fallar "Quedan $libres GB libres donde Docker guarda las imágenes ($carpeta); hacen falta al menos $DISCO_MINIMO_GB."
    fi
    if [ "$libres" -lt "$DISCO_RECOMENDADO_GB" ]; then
        aviso "Quedan $libres GB libres en $carpeta: alcanza para instalar, pero los videos y las copias de seguridad van a necesitar más (se recomiendan $DISCO_RECOMENDADO_GB GB)."
    else
        bien "$libres GB libres en $carpeta"
    fi
}

comprobar_memoria() {
    local memoria
    memoria="$(awk '/^MemTotal:/ { printf "%d", $2 / 1024 }' /proc/meminfo 2>/dev/null || true)"
    [ -n "$memoria" ] || return 0

    if [ "$memoria" -lt "$MEMORIA_MINIMA_MB" ]; then
        aviso "El servidor tiene $memoria MB de RAM: con menos de 2 GB la construcción de las imágenes puede fallar y la plataforma irá lenta."
    else
        bien "$memoria MB de RAM"
    fi
}

comprobar_puerto() {
    local actual otro=""
    if [ -f "$ARCHIVO_ENV" ]; then actual="$(puerto)"; else actual="${APP_PORT:-80}"; fi

    if servicio_corriendo web; then
        bien "El puerto $actual es de la plataforma, que ya está corriendo"
        return 0
    fi
    if ! puerto_ocupado "$actual"; then
        bien "El puerto $actual está libre"
        return 0
    fi

    interactivo ||
        fallar "El puerto $actual ya lo usa otro programa. Elige otro: APP_PORT=8080 ./produccion.sh instalar (o cámbialo en el .env, y también en APP_URL si lo lleva)."

    aviso "El puerto $actual ya lo usa otro programa en este servidor."
    while :; do
        preguntar otro "Puerto para la plataforma" "8080"
        if [[ "$otro" =~ ^[0-9]{2,5}$ ]] && ! puerto_ocupado "$otro"; then break; fi
        aviso "El puerto «$otro» no sirve: está ocupado o no es un número."
        otro=""
    done

    if [ -f "$ARCHIVO_ENV" ]; then
        poner APP_PORT "$otro"
        aviso "Si APP_URL lleva el puerto, cámbialo también en el .env."
    else
        APP_PORT="$otro"
    fi
    bien "La plataforma escuchará en el puerto $otro"
}

# Una base que se quedó sin su .env no se puede abrir con contraseñas
# nuevas, y con otra APP_KEY los documentos cifrados se pierden: mejor parar.
comprobar_que_no_hay_una_base_sin_su_env() {
    [ "$ARCHIVO_ENV" = "$RAIZ/.env" ] || return 0
    command -v docker >/dev/null 2>&1 && docker info >/dev/null 2>&1 || return 0
    docker volume inspect "${PROYECTO}_datos_postgres" >/dev/null 2>&1 || return 0

    fallar "Ya hay una base de datos de una instalación anterior (volumen ${PROYECTO}_datos_postgres), pero el .env no trae su clave ni su contraseña: con unas nuevas quedaría inservible.
    Recupera el .env de esa instalación y ponlo en $RAIZ/.env.
    Si este servidor es de pruebas y quieres empezar de cero (borra la base, las fotos y los documentos subidos):
      docker compose -f $COMPOSE_ARCHIVO down -v"
}

# ---------------------------------------------------------------------------
# Pasos
# ---------------------------------------------------------------------------

configurar() {
    paso "Configuración (.env)"

    local nuevo=""
    if [ -f "$ARCHIVO_ENV" ]; then
        bien "Usa el .env que ya existe: sus claves y contraseñas no se tocan"
        completar_con_el_ejemplo
    else
        comprobar_que_no_hay_una_base_sin_su_env
        (umask 077 && cp "$EJEMPLO" "$ARCHIVO_ENV")
        nuevo=1
        bien "Creó el .env a partir de .env.produccion.example"
    fi
    chmod 600 "$ARCHIVO_ENV"

    if [ -n "$nuevo" ]; then
        if [ -n "${APP_PORT:-}" ]; then
            validar "$APP_PORT" '^[0-9]{2,5}$' "APP_PORT tiene que ser un número de puerto, no «$APP_PORT»."
            poner APP_PORT "$APP_PORT"
        fi
        [ -z "${DIRECTORIO_COPIAS:-}" ] || poner DIRECTORIO_COPIAS "$DIRECTORIO_COPIAS"
    fi

    if [ -z "$nuevo" ] && { es_marca "$(valor APP_KEY)" || es_marca "$(valor DB_DUENO_CONTRASENA)"; }; then
        comprobar_que_no_hay_una_base_sin_su_env
    fi
    generar_si_falta APP_KEY "$(clave_de_la_aplicacion)" "la clave de la aplicación"
    generar_si_falta DB_PASSWORD "$(secreto)" "la contraseña con la que entra la aplicación a la base"
    generar_si_falta DB_DUENO_CONTRASENA "$(secreto)" "la contraseña del dueño de la base"

    configurar_direccion "$nuevo"
    configurar_google
    configurar_correo

    if grep -q '=<' "$ARCHIVO_ENV"; then
        fallar "Quedaron marcas <...> sin llenar en el .env: $(grep '=<' "$ARCHIVO_ENV" | cut -d= -f1 | tr '\n' ' ')"
    fi
    bien "El .env está completo y solo lo lee su dueño (permisos 600)"
}

# Un .env de una versión anterior no trae las variables que el ejemplo ganó
# después: se añaden con el valor del ejemplo, y las que son marcas <...> se
# llenan como en una instalación nueva.
completar_con_el_ejemplo() {
    local linea clave anadidas=0
    [ -z "$(tail -c 1 "$ARCHIVO_ENV")" ] || printf '\n' >> "$ARCHIVO_ENV"
    while IFS= read -r linea || [ -n "$linea" ]; do
        case "$linea" in [A-Z]*=*) ;; *) continue ;; esac
        clave="${linea%%=*}"
        grep -q "^$clave=" "$ARCHIVO_ENV" && continue
        printf '%s\n' "$linea" >> "$ARCHIVO_ENV"
        anadidas=$((anadidas + 1))
    done < "$EJEMPLO"
    [ "$anadidas" -eq 0 ] || bien "Añadió $anadidas variable(s) del ejemplo que le faltaban al .env"
}

configurar_direccion() {  # configurar_direccion ES_NUEVO
    if hay_que_llenar APP_URL "$1"; then
        local direccion="${APP_URL:-}"
        if [ -z "$direccion" ]; then
            interactivo || fallar "Falta APP_URL: la dirección con la que se entrará, por ejemplo APP_URL=https://laboratorio.$DOMINIO_SUGERIDO"
            nota "La dirección con la que se entrará a la plataforma. Con HTTPS si ya lo hay"
            nota "(https://laboratorio.$DOMINIO_SUGERIDO); si todavía no, la IP del servidor."
            preguntar direccion "Dirección" "$(direccion_sugerida)"
        fi
        direccion="${direccion%/}"
        validar "$direccion" '^https?://[A-Za-z0-9.-]+(:[0-9]{1,5})?$' \
            "«$direccion» no sirve como APP_URL: tiene que ser http:// o https://, el nombre o la IP y, si hace falta, el puerto. Sin carpetas al final."
        poner APP_URL "$direccion"
        bien "APP_URL=$direccion"
    fi

    # La cookie segura solo viaja por HTTPS: con http:// nadie podría iniciar
    # sesión, y con https:// no se debe apagar.
    if es_https; then
        asegurar SESSION_SECURE_COOKIE true
    else
        asegurar SESSION_SECURE_COOKIE false
        aviso "Sin HTTPS la sesión viaja sin cifrar y Google no deja entrar salvo desde localhost. Sirve para probar; para usarla de verdad hace falta HTTPS."
    fi

    [ -z "$(valor PROXIES_DE_CONFIANZA)" ] || return 0
    local proxies="${PROXIES_DE_CONFIANZA:-}"
    if [ -z "$proxies" ] && es_https && interactivo; then
        nota "Si el HTTPS lo pone un proxy de la universidad, su IP (pídesela al ingeniero). Vacío si no hay proxy."
        preguntar proxies "IP del proxy" ""
    fi
    if [ -n "$proxies" ]; then
        validar "$proxies" '^(\*|[0-9A-Fa-f.:]+(/[0-9]{1,3})?)( *, *[0-9A-Fa-f.:]+(/[0-9]{1,3})?)*$' \
            "PROXIES_DE_CONFIANZA tiene que ser una IP o un rango (10.0.0.5 o 10.0.0.0/24), o varias separadas por comas."
        poner PROXIES_DE_CONFIANZA "$proxies"
        bien "PROXIES_DE_CONFIANZA=$proxies"
    elif es_https; then
        aviso "Con https y sin PROXIES_DE_CONFIANZA: si el HTTPS lo pone un proxy, la aplicación no sabrá que la petición era segura y Google rechazará la dirección de retorno. Pon su IP en el .env cuando la tengas."
    fi
}

direccion_sugerida() {
    local ip p
    ip="$(hostname -I 2>/dev/null | awk '{ print $1 }' || true)"
    p="$(puerto)"
    [ "$p" = 80 ] && p="" || p=":$p"
    printf 'http://%s%s' "${ip:-localhost}" "$p"
}

configurar_google() {
    [ -z "$(valor GOOGLE_CLIENT_ID)" ] || return 0

    local id="${GOOGLE_CLIENT_ID:-}" clave="${GOOGLE_CLIENT_SECRET:-}" dominio="${GOOGLE_DOMINIO_INSTITUCIONAL:-}"
    if [ -z "$id" ] && interactivo; then
        nota "Entrada con Google. Si todavía no tienes las credenciales, deja esto vacío: se llenan después en el .env."
        preguntar id "GOOGLE_CLIENT_ID" ""
    fi
    [ -n "$id" ] || return 0

    if [ -z "$clave" ] && interactivo; then preguntar_oculto clave "GOOGLE_CLIENT_SECRET"; fi
    if [ -z "$dominio" ] && interactivo; then preguntar dominio "Dominio de las cuentas que pueden entrar" "$DOMINIO_SUGERIDO"; fi
    [ -n "$clave" ] || fallar "Con GOOGLE_CLIENT_ID hace falta también GOOGLE_CLIENT_SECRET."
    [ -n "$dominio" ] || fallar "Con GOOGLE_CLIENT_ID hace falta también GOOGLE_DOMINIO_INSTITUCIONAL: sin él, en producción nadie entra."

    validar "$id" '^[A-Za-z0-9._-]+$' "GOOGLE_CLIENT_ID no parece un identificador de Google (termina en .apps.googleusercontent.com)."
    validar "$clave" '^[A-Za-z0-9._~-]+$' "GOOGLE_CLIENT_SECRET tiene caracteres que no son de una clave de Google."
    validar "$dominio" '^[a-z0-9.-]+\.[a-z]{2,}$' "GOOGLE_DOMINIO_INSTITUCIONAL tiene que ser un dominio, como $DOMINIO_SUGERIDO."

    poner GOOGLE_CLIENT_ID "$id"
    poner GOOGLE_CLIENT_SECRET "$clave"
    poner GOOGLE_DOMINIO_INSTITUCIONAL "$dominio"
    bien "Entrada con Google para las cuentas de $dominio"
}

configurar_correo() {
    # Como la entrada con Google: mientras falte, se vuelve a preguntar.
    es_marca "$(valor MAIL_HOST)" || [ "$(valor MAIL_MAILER)" = log ] || return 0

    local correo="${CORREO_LABORATORIO:-}" clave="${CORREO_CONTRASENA_DE_APLICACION:-}"
    if [ -z "$correo" ] && interactivo; then
        nota "El correo del laboratorio, la cuenta de Google desde la que salen los avisos. Vacío si todavía"
        nota "no tienes su contraseña de aplicación: mientras tanto los avisos quedan en el registro."
        preguntar correo "Correo del laboratorio" ""
    fi

    if [ -z "$correo" ]; then
        poner MAIL_MAILER log
        poner MAIL_HOST localhost
        poner MAIL_USERNAME null
        poner MAIL_PASSWORD null
        poner MAIL_FROM_ADDRESS "$REMITENTE_SIN_CORREO"
        aviso "Sin correo: los avisos quedan en el registro (docker compose -f $COMPOSE_ARCHIVO logs queue) hasta que se llenen los MAIL_* del .env."
        return 0
    fi

    validar "$correo" '^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$' "«$correo» no es un correo."
    if [ -z "$clave" ] && interactivo; then preguntar_oculto clave "Contraseña de aplicación de $correo (16 letras)"; fi
    # Google la muestra en grupos de cuatro con espacios; se usa sin ellos.
    clave="${clave// /}"
    [ -n "$clave" ] || fallar "Con el correo del laboratorio hace falta su contraseña de aplicación (CORREO_CONTRASENA_DE_APLICACION)."
    validar "$clave" '^[A-Za-z0-9]+$' "La contraseña de aplicación de Google son 16 letras; la que llegó tiene otros caracteres."

    poner MAIL_MAILER smtp
    poner MAIL_HOST smtp.gmail.com
    poner MAIL_PORT 587
    poner MAIL_USERNAME "$correo"
    poner MAIL_PASSWORD "$clave"
    poner MAIL_FROM_ADDRESS "$correo"
    bien "Los avisos salen de $correo por el SMTP de Google"
}

preparar_carpeta_de_copias() {
    paso "Carpeta de las copias de seguridad"
    local carpeta
    carpeta="$(valor DIRECTORIO_COPIAS)"
    [ -n "$carpeta" ] || fallar "Falta DIRECTORIO_COPIAS en el .env."

    if [ -d "$carpeta" ]; then
        bien "$carpeta ya existe"
        return 0
    fi
    # Si la creara Docker al montarla, quedaría legible para todos; las
    # copias tienen datos personales.
    if install -d -m 700 "$carpeta" 2>/dev/null; then
        bien "Creó $carpeta (solo la lee su dueño)"
        return 0
    fi
    if command -v sudo >/dev/null 2>&1 && ejecutar sudo install -d -m 700 "$carpeta"; then
        bien "Creó $carpeta (solo la lee root)"
        return 0
    fi
    fallar "No pude crear $carpeta. Créala con: sudo install -d -m 700 $carpeta"
}

obtener_imagenes() {
    if [ -n "$IMAGENES" ]; then
        cargar_imagenes "$IMAGENES"
        return 0
    fi

    paso "Imágenes (la primera vez tarda entre 5 y 15 minutos)"
    compose build ||
        fallar "No se pudieron construir las imágenes. Si el error habla de la red (timeout, could not resolve, TLS), el servidor no sale a internet o lo hace por un proxy: mira «Sin internet» en docs/instalacion-en-el-servidor.md."
    compose pull --quiet db copias ||
        fallar "No se pudo descargar la imagen de PostgreSQL. Mira «Sin internet» en docs/instalacion-en-el-servidor.md."
    bien "Imágenes listas"
}

cargar_imagenes() {  # cargar_imagenes ARCHIVO: las que dejó "empaquetar"
    local archivo=$1 de_la_imagen del_servidor
    paso "Imágenes desde $archivo"
    [ -f "$archivo" ] || fallar "No encuentro $archivo."

    if [ -f "$archivo.sha256" ]; then
        (cd "$(dirname "$archivo")" && suma_sha256 -c "$(basename "$archivo").sha256" >/dev/null) ||
            fallar "$archivo está dañado: no coincide con su suma ($archivo.sha256). Vuelve a copiarlo."
        bien "La suma SHA-256 coincide"
    fi
    ejecutar docker load -i "$archivo"

    de_la_imagen="$(docker image inspect --format '{{.Architecture}}' "$IMAGEN_APP")"
    del_servidor="$(docker version --format '{{.Server.Arch}}')"
    [ "$de_la_imagen" = "$del_servidor" ] ||
        fallar "Las imágenes son para $de_la_imagen y este servidor es $del_servidor: empaquétalas con PLATAFORMA=linux/$del_servidor."
    bien "Imágenes cargadas"
}

migrar() {
    paso "Base de datos: tablas, roles y usuario de la aplicación"
    compose run --rm migraciones ||
        fallar "Las migraciones fallaron (el detalle está arriba). Si dice «password authentication failed», el .env no es el de la base que hay en este servidor."
    bien "Base al día"
}

levantar() {
    paso "Servicios"
    compose up -d --remove-orphans
    esperar_a_la_plataforma
}

esperar_a_la_plataforma() {
    local codigo="" intento
    printf '    Esperando a que responda'
    for intento in $(seq 1 60); do
        codigo="$(codigo_http /up)"
        if [ "$codigo" = 200 ]; then
            printf '\n'
            bien "Responde en el puerto $(puerto) (intento $intento)"
            return 0
        fi
        printf '.'
        sleep 3
    done
    printf '\n'
    compose_callado ps
    fallar "La plataforma no respondió en 3 minutos (último código: ${codigo:-ninguno}). Mira los registros: docker compose -f $COMPOSE_ARCHIVO logs --tail=50 app web"
}

cuantos_administradores() {
    # "admin" es el valor de Rol::Admin.
    compose_callado exec -T db psql -U "$(valor DB_DUENO_USUARIO)" -d "$(valor DB_DATABASE)" -tAc \
        "select count(*) from model_has_roles m join roles r on r.id = m.role_id where r.name = 'admin'" 2>/dev/null |
        tr -dc '0-9' || true
}

primer_administrador() {
    paso "Primer ADMIN"
    local correo="${ADMIN_CORREO:-}" nombre="${ADMIN_NOMBRE:-}" ya_hay
    ya_hay="$(cuantos_administradores)"

    if [ -z "$correo" ] && [ "${ya_hay:-0}" -gt 0 ]; then
        bien "Ya hay $ya_hay cuenta(s) con el rol de ADMIN"
        return 0
    fi
    if [ -z "$correo" ] && interactivo; then
        nota "La entrada es solo con Google y no crea cuentas: la primera se crea aquí. Usa el correo"
        nota "institucional de quien va a administrar la plataforma."
        preguntar correo "Correo del ADMIN (vacío para hacerlo después)" ""
    fi
    if [ -z "$correo" ]; then
        aviso "Todavía no hay ADMIN. Créalo cuando quieras: ./produccion.sh administrador correo@$DOMINIO_SUGERIDO \"Nombre Apellido\""
        return 0
    fi
    if [ -z "$nombre" ] && interactivo; then
        preguntar nombre "Nombre completo (si la cuenta ya existe, déjalo vacío)" ""
    fi

    compose exec -T app php artisan usuarios:crear-administrador "$correo" ${nombre:+"$nombre"} ||
        aviso "No se creó el ADMIN (el motivo está arriba). Inténtalo otra vez con: ./produccion.sh administrador $correo \"Nombre Apellido\""
}

copia_antes_de_migrar() {
    paso "Copia de seguridad antes de migrar"
    if ! servicio_corriendo copias; then
        aviso "El servicio de copias no está corriendo: se actualiza sin copia previa."
        return 0
    fi
    compose exec -T copias /scripts/hacer-copia.sh ||
        fallar "La copia de seguridad falló y no sigo sin ella (el detalle está arriba)."
}

revisar_configuracion() {  # avisos de lo que falta para usarla de verdad; no cambia nada
    es_https || aviso "Sin HTTPS (APP_URL=$(valor APP_URL)): sirve para probar, no para usarla de verdad."
    if es_https && [ -z "$(valor PROXIES_DE_CONFIANZA)" ]; then
        aviso "Con https y sin PROXIES_DE_CONFIANZA: si el HTTPS lo pone un proxy, Google rechazará la dirección de retorno."
    fi
    if es_https && [ "$(valor SESSION_SECURE_COOKIE)" != true ]; then
        aviso "Con https, SESSION_SECURE_COOKIE debería ser true (./produccion.sh instalar lo corrige)."
    fi
    if [ -z "$(valor GOOGLE_CLIENT_ID)" ]; then
        aviso "Sin entrada con Google (GOOGLE_CLIENT_ID vacío): nadie puede iniciar sesión todavía."
        nota "Dirección de retorno que hay que registrar en Google: $(valor APP_URL)/acceso/google/volver"
    fi
    [ "$(valor MAIL_MAILER)" != log ] || aviso "Sin correo (MAIL_MAILER=log): los avisos quedan en el registro de queue."
}

resumen_de_la_instalacion() {
    local direccion
    direccion="$(valor APP_URL)"
    paso "Listo"
    nota "La plataforma:          $direccion"
    nota "El panel del ADMIN:     $direccion/admin"
    nota "El panel de trabajo:    $direccion/panel"
    printf '\n'
    revisar_configuracion
    printf '\n'
    nota "${NEGRITA}Guarda una copia del .env fuera de este servidor.${NORMAL} Tiene la APP_KEY: sin ella no se"
    nota "recupera el documento de identidad de nadie, aunque haya copias de la base."
    nota "Si cambias el .env, vuelve a correr ./produccion.sh instalar para que los contenedores lo lean."
    nota "Para revisar que todo sigue bien: ./produccion.sh verificar"
}

# ---------------------------------------------------------------------------
# Órdenes
# ---------------------------------------------------------------------------

instalar() {
    requisitos
    comprobar_puerto
    configurar
    solo_el_env_manda
    preparar_carpeta_de_copias
    obtener_imagenes
    migrar
    levantar
    primer_administrador
    resumen_de_la_instalacion
}

actualizar() {
    requisitos
    [ -f "$RAIZ/.env" ] || fallar "No hay .env: esto es una instalación nueva. Corre ./produccion.sh instalar"
    solo_el_env_manda

    if [ -z "$SIN_GIT" ]; then
        paso "Código"
        if git -C "$RAIZ" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
            ejecutar git -C "$RAIZ" pull --ff-only ||
                fallar "git pull no pudo traer la versión nueva. ¿Alguien cambió archivos a mano en el servidor? Míralo con git status. Para actualizar con el código tal como está: --sin-git"
            bien "Código en $(git -C "$RAIZ" log -1 --format='%h %s')"
        else
            aviso "Esta carpeta no es un repositorio git: se usa el código tal como está."
        fi
    fi

    copia_antes_de_migrar
    obtener_imagenes
    migrar
    levantar
    paso "Actualizada"
    revisar_configuracion
}

verificar() {
    paso "Requisitos"
    requisitos_basicos
    [ -f "$RAIZ/.env" ] || fallar "No hay .env: la plataforma no está instalada en esta carpeta. Corre ./produccion.sh instalar"
    solo_el_env_manda

    local problemas=0 servicio contenedor salud ruta codigo roles administradores ultima
    paso "Servicios"
    for servicio in app queue programador web db copias; do
        contenedor="$(compose_callado ps -q --status running "$servicio" 2>/dev/null || true)"
        if [ -z "$contenedor" ]; then
            mal "$servicio no está corriendo"
            problemas=$((problemas + 1))
            continue
        fi
        salud="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}sin sonda{{end}}' "$contenedor")"
        if [ "$salud" = unhealthy ]; then
            mal "$servicio corre pero no responde (unhealthy)"
            problemas=$((problemas + 1))
        else
            bien "$servicio ($salud)"
        fi
    done

    paso "Respuestas"
    for ruta in /up /; do
        codigo="$(codigo_http "$ruta")"
        if [ "$codigo" = 200 ]; then
            bien "$ruta responde 200"
        else
            mal "$ruta responde ${codigo:-nada}"
            problemas=$((problemas + 1))
        fi
    done

    paso "Datos"
    roles="$(compose_callado exec -T db psql -U "$(valor DB_DUENO_USUARIO)" -d "$(valor DB_DATABASE)" -tAc 'select count(*) from roles' 2>/dev/null | tr -dc '0-9' || true)"
    if [ "${roles:-0}" -ge 6 ]; then
        bien "Los $roles roles están creados"
    else
        mal "Faltan roles (hay ${roles:-0}). Corre: docker compose -f $COMPOSE_ARCHIVO run --rm migraciones"
        problemas=$((problemas + 1))
    fi
    administradores="$(cuantos_administradores)"
    if [ "${administradores:-0}" -gt 0 ]; then
        bien "$administradores cuenta(s) con el rol de ADMIN"
    else
        aviso "No hay ningún ADMIN. Créalo con: ./produccion.sh administrador correo@$DOMINIO_SUGERIDO \"Nombre Apellido\""
    fi
    ultima="$(compose_callado exec -T copias sh -c 'ls -1 /copias | grep -E "^20..-..-.._....$" | tail -n 1' 2>/dev/null || true)"
    if [ -n "$ultima" ]; then
        bien "Última copia de seguridad: $ultima"
    else
        aviso "Todavía no hay copias de seguridad (la diaria es a las $(valor COPIAS_HORA):$(valor COPIAS_MINUTO))."
    fi

    paso "Configuración"
    revisar_configuracion
    comprobar_disco

    [ "$problemas" -eq 0 ] || fallar "$problemas problema(s). Los registros: docker compose -f $COMPOSE_ARCHIVO logs --tail=50 <servicio>"
    paso "Todo en orden"
}

administrador() {  # administrador [CORREO] [NOMBRE]
    requisitos_basicos
    [ -f "$RAIZ/.env" ] || fallar "No hay .env: la plataforma no está instalada en esta carpeta. Corre ./produccion.sh instalar"
    solo_el_env_manda
    servicio_corriendo app || fallar "La plataforma no está corriendo aquí. Corre primero ./produccion.sh instalar"

    local correo="${1:-}" nombre="${2:-}"
    if [ -z "$correo" ] && interactivo; then preguntar correo "Correo del ADMIN" ""; fi
    [ -n "$correo" ] || fallar "Uso: ./produccion.sh administrador correo@$DOMINIO_SUGERIDO \"Nombre Apellido\""
    if [ -z "$nombre" ] && interactivo; then preguntar nombre "Nombre completo (si la cuenta ya existe, déjalo vacío)" ""; fi

    compose exec -T app php artisan usuarios:crear-administrador "$correo" ${nombre:+"$nombre"}
}

empaquetar() {  # empaquetar [CARPETA]: en un equipo con internet
    paso "Requisitos"
    requisitos_basicos

    local destino="${1:-$RAIZ/imagenes-docker}" version archivo rama referencias=(HEAD)
    version="$(git -C "$RAIZ" rev-parse --short HEAD)"
    if [ -n "$(git -C "$RAIZ" status --porcelain --untracked-files=no)" ]; then
        aviso "Hay cambios sin commitear: entrarían en las imágenes pero no en el código empaquetado."
        if ! { interactivo && confirmar "¿Seguir de todos modos?"; }; then
            fallar "Commitea o descarta los cambios y vuelve a empaquetar."
        fi
    fi
    mkdir -p "$destino"
    archivo="$destino/laboratorio-imagenes-$version.tar.gz"

    paso "Imágenes para $PLATAFORMA"
    ejecutar docker build --platform "$PLATAFORMA" --target produccion -t "$IMAGEN_APP" -f docker/php/Dockerfile .
    ejecutar docker build --platform "$PLATAFORMA" --target web -t "$IMAGEN_WEB" -f docker/php/Dockerfile .
    ejecutar docker pull --platform "$PLATAFORMA" "$IMAGEN_BASE"
    ejecutar docker pull --platform "$PLATAFORMA" "$IMAGEN_COPIAS"

    paso "Archivo de imágenes (unos minutos)"
    docker save "$IMAGEN_APP" "$IMAGEN_WEB" "$IMAGEN_BASE" "$IMAGEN_COPIAS" | gzip -1 > "$archivo"
    (cd "$destino" && suma_sha256 "$(basename "$archivo")" > "$(basename "$archivo").sha256")
    bien "$archivo ($(du -h "$archivo" | cut -f1))"

    paso "Código"
    # Solo lo commiteado: una copia de la carpeta se llevaría el .env de
    # desarrollo y los documentos de prueba de storage/. Y con toda la
    # historia: de un clon superficial (--depth) no sale un bundle que se
    # pueda clonar.
    if [ "$(git -C "$RAIZ" rev-parse --is-shallow-repository)" = true ]; then
        ejecutar git -C "$RAIZ" fetch --unshallow ||
            fallar "Este clon no tiene la historia completa y no se pudo traer (git fetch --unshallow)."
    fi
    rama="$(git -C "$RAIZ" symbolic-ref -q --short HEAD || true)"
    [ -z "$rama" ] || referencias+=("$rama")
    ejecutar git -C "$RAIZ" bundle create "$destino/laboratorio-codigo-$version.bundle" "${referencias[@]}"

    paso "Listo"
    nota "Copia la carpeta $destino a una memoria USB. En el servidor:"
    nota "  git clone laboratorio-codigo-$version.bundle /srv/laboratorio && cd /srv/laboratorio"
    nota "  ./produccion.sh instalar --imagenes /ruta/a/laboratorio-imagenes-$version.tar.gz"
}

ayuda() {
    sed -n '2,16p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
    printf '\nOpciones:\n'
    printf '  --imagenes ARCHIVO   (instalar, actualizar) carga las imágenes de "empaquetar" en vez de construirlas\n'
    printf '  --sin-git            (actualizar) no hace git pull: usa el código tal como está\n'
}

principal() {
    local orden="${1:-ayuda}" argumentos=()
    [ $# -eq 0 ] || shift
    while [ $# -gt 0 ]; do
        case "$1" in
            --imagenes) [ $# -ge 2 ] || fallar "Falta el archivo después de --imagenes."; IMAGENES="$2"; shift 2 ;;
            --imagenes=*) IMAGENES="${1#*=}"; shift ;;
            --sin-git) SIN_GIT=1; shift ;;
            -h | --help) orden=ayuda; shift ;;
            -*) fallar "No conozco la opción $1. Mira: ./produccion.sh ayuda" ;;
            *) argumentos+=("$1"); shift ;;
        esac
    done
    if [ -n "$IMAGENES" ]; then
        IMAGENES="$(desde_donde_se_corrio "$IMAGENES")"
        [ -f "$IMAGENES" ] || fallar "No encuentro el archivo de imágenes $IMAGENES."
    fi

    case "$orden" in
        instalar) instalar ;;
        actualizar) actualizar ;;
        verificar) verificar ;;
        administrador) administrador ${argumentos[@]+"${argumentos[@]}"} ;;
        configurar) configurar ;;
        empaquetar)
            if [ ${#argumentos[@]} -gt 0 ]; then empaquetar "$(desde_donde_se_corrio "${argumentos[0]}")"; else empaquetar; fi ;;
        ayuda | -h | --help) ayuda ;;
        *) ayuda; exit 2 ;;
    esac
}

# Todo está en funciones y esta es la última línea: "actualizar" hace git
# pull, que puede cambiar este mismo archivo mientras corre.
principal "$@"; exit $?
