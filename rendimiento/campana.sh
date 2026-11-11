#!/usr/bin/env bash
# Campaña completa de pruebas de rendimiento, reproducible en un solo comando.
#
# Orquesta el arnés de rendimiento/ (k6, pgbench, navegador, nginx, muestreo de
# recursos) según docs/rendimiento/plan-de-pruebas.md, para que el administrador
# del servidor repita exactamente esta campaña en el servidor institucional
# ANTES de producción.
#
#   RENDIMIENTO_BASE_DESECHABLE=si rendimiento/campana.sh [paso ...]
#
# Sin pasos corre todos, en este orden:
#   preparar        DESTRUCTIVO: `down -v`, migra, siembra roles, genera el volumen y las sesiones
#   bd-referencia   pgbench TPC-B (línea base del hardware)
#   bd-planes       EXPLAIN de las consultas calientes
#   bd-consultas    pgbench con las consultas reales (cada una a 10 clientes) y la mezcla (1, 10, 20)
#   humo            k6 humo
#   rnf01           k6 carga-rnf01, desde una sola IP (producción hoy)
#   mixta           k6 carga-mixta
#   capacidad       k6 capacidad
#   pico-una-ip     k6 pico, topes de nginx compartidos (hallazgo B3.8)
#   pico-ip-real    k6 pico, una IP por usuario (mide la aplicación)
#   estres-una-ip   k6 estres, una sola IP
#   estres-ip-real  k6 estres, una IP por usuario
#   concurrencia    k6 concurrencia de escrituras (escribe en la base)
#   descargas       k6 reportes y topes
#   cola            trabajos por segundo del único worker de la cola
#   navegador       Web Vitals y RNF10 con navegador real
#   recorridos      recorridos de punta a punta (escribe en la base)
#   resistencia     k6 resistencia (la más larga)
#   x5              prepara con factor de volumen 5 y repite humo, rnf01, bd-planes,
#                   la mezcla de bd-consultas y web-vitals de las páginas del panel
#
# Reanudar: CAMPANA=campana-AAAAMMDD-HHMM (la carpeta que ya existe) y, o bien los pasos
# que faltan, o DESDE=<paso> para correr ese y todos los siguientes.
#
# Variables (todas opcionales):
#   COMPOSE          comando de compose del SUT (por defecto producción + override de rendimiento)
#   BASE_URL         sitio a probar (http://localhost)
#   FACTOR           factor de volumen de `preparar` (1)
#   RAPIDO           "si" reduce todo al mínimo para validar el script (segundos por paso)
#   CAMPANA          nombre de la carpeta de resultados (campana-<AAAAMMDD-HHMM>)
#   DESDE            paso desde el que se corre (con todos los siguientes)
#   PAUSA            segundos entre pasos para que los topes por minuto se vacíen (60)
#   CPUS_SUT         cpuset de los contenedores del SUT (p. ej. 0-2); vacío = no se toca
#   CPUS_GENERADOR   cpuset de k6 y pgbench (p. ej. 3); vacío = no se toca (generador en otra máquina)
#   SESIONES_POR_ROL sesiones forjadas por rol (300)
#   Duraciones, con su valor por defecto de la campaña completa:
#     REF_ESCALA=50 REF_DURACION=60 REF_CLIENTES="1 4 8 16"      bd-referencia
#     BD_DURACION_ESCENARIO=15 BD_DURACION_MEZCLA=60             bd-consultas (≈ 10 min en total)
#     RNF01_PERSONAS=150 RNF01_VENTANA=5m                        rnf01
#     MIXTA_VUS=300 MIXTA_DURACION=10m                           mixta
#     CAP_ESCALONES="10,20,30,40,50,60,80" CAP_DURACION_ESCALON=90s   capacidad (≈ 12 min)
#     RESISTENCIA_DURACION=30m RESISTENCIA_VUS=50                resistencia
#     NAV_ITERACIONES=5 REC_ITERACIONES=3 NAV_LIMITE=10m         navegador y recorridos (tope por proceso de k6)
#     COLA_TRABAJOS=300                                          cola
#   pico y estrés usan los valores por defecto de sus scripts (estrés: 5 → 115 r/s, +10 por minuto).
#
# Cada paso deja su carpeta en rendimiento/resultados/<CAMPANA>/<NN>-<paso>/ con lo que
# midió, el muestreo de recursos, el resumen de nginx y la ficha; al final, indice.txt y
# entorno.txt en la carpeta de la campaña, y avance.log con una línea por evento.
# Un paso que no cumple sus umbrales NO detiene la campaña: se anota y se sigue.
#
# Un solo paso a la vez y sin nada más corriendo en la máquina: las mediciones
# compiten por CPU. Si la CPU del generador pasa del 90 %, el paso mide al generador
# y no al SUT (criterio de suspensión del plan): indice.txt lo advierte.
set -euo pipefail

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RENDIMIENTO="$RAIZ/rendimiento"

COMPOSE="${COMPOSE:-docker compose -f docker-compose.produccion.yml -f rendimiento/docker-compose.rendimiento.yml}"
BASE_URL="${BASE_URL:-http://localhost}"
FACTOR="${FACTOR:-1}"
RAPIDO="${RAPIDO:-no}"
CPUS_SUT="${CPUS_SUT:-}"
CPUS_GENERADOR="${CPUS_GENERADOR:-}"
K6_IMAGEN="${K6_IMAGEN:-grafana/k6:2.3.0}"
K6_IMAGEN_NAVEGADOR="${K6_IMAGEN_NAVEGADOR:-grafana/k6:2.3.0-with-browser}"
SERVICIOS="db app queue programador web"

# Valor según el modo: rap <valor rápido> <valor de la campaña completa>.
rap() { if [[ "$RAPIDO" == "si" ]]; then echo "$1"; else echo "$2"; fi; }

PAUSA="${PAUSA:-$(rap 2 60)}"
SESIONES_POR_ROL="${SESIONES_POR_ROL:-$(rap 30 300)}"
REF_ESCALA="${REF_ESCALA:-$(rap 1 50)}"
REF_DURACION="${REF_DURACION:-$(rap 3 60)}"
REF_CLIENTES="${REF_CLIENTES:-$(rap "1 2" "1 4 8 16")}"
BD_DURACION_ESCENARIO="${BD_DURACION_ESCENARIO:-$(rap 1 15)}"
BD_DURACION_MEZCLA="${BD_DURACION_MEZCLA:-$(rap 3 60)}"
RNF01_PERSONAS="${RNF01_PERSONAS:-$(rap 10 150)}"
RNF01_VENTANA="${RNF01_VENTANA:-$(rap 20s 5m)}"
MIXTA_VUS="${MIXTA_VUS:-$(rap 10 300)}"
MIXTA_DURACION="${MIXTA_DURACION:-$(rap 30s 10m)}"
CAP_ESCALONES="${CAP_ESCALONES:-$(rap "5,10" "10,20,30,40,50,60,80")}"
CAP_DURACION_ESCALON="${CAP_DURACION_ESCALON:-$(rap 10s 90s)}"
RESISTENCIA_DURACION="${RESISTENCIA_DURACION:-$(rap 1m 30m)}"
RESISTENCIA_VUS="${RESISTENCIA_VUS:-$(rap 5 50)}"
NAV_ITERACIONES="${NAV_ITERACIONES:-$(rap 1 5)}"
REC_ITERACIONES="${REC_ITERACIONES:-$(rap 1 3)}"
COLA_TRABAJOS="${COLA_TRABAJOS:-300}"
# Tope de cada proceso de navegador: k6 browser se cuelga a veces (Chromium sin responder,
# 0 % de CPU, sin fin). Pasado el tope se mata el contenedor y se reintenta.
NAV_LIMITE="${NAV_LIMITE:-$(rap 2m 10m)}"
LISTA_PAGINAS_NAV="${LISTA_PAGINAS_NAV:-portada escenario hoy-estudiante hoy-docente hoy-administrativo calendario bandeja tablero}"

# Orden de la campaña; la posición fija el número de la carpeta, así reanudar no la cambia.
PASOS=(preparar bd-referencia bd-planes bd-consultas humo rnf01 mixta capacidad pico-una-ip pico-ip-real
    estres-una-ip estres-ip-real concurrencia descargas cola navegador recorridos resistencia x5)

CAMPANA="${CAMPANA:-campana-$(date +%Y%m%d-%H%M)}"
DIRECTORIO="$RENDIMIENTO/resultados/$CAMPANA"
AVANCE="$DIRECTORIO/avance.log"

dc() { $COMPOSE "$@"; }

avance() {
    mkdir -p "$DIRECTORIO"
    echo "$(date '+%Y-%m-%d %H:%M:%S') $*" | tee -a "$AVANCE" >&2
}

# ---------------------------------------------------------------------------
# Comprobaciones previas
# ---------------------------------------------------------------------------
if [[ "${RENDIMIENTO_BASE_DESECHABLE:-}" != "si" ]]; then
    cat >&2 <<'AVISO'
Esta campaña escribe datos de prueba, forja sesiones y borra volúmenes.
Solo corre contra un servidor SIN datos reales (antes de producción) o contra una copia.
Confírmalo con RENDIMIENTO_BASE_DESECHABLE=si y vuelve a lanzarla.
AVISO
    exit 1
fi

numero_de() {
    local i
    for i in "${!PASOS[@]}"; do
        if [[ "${PASOS[$i]}" == "$1" ]]; then
            printf '%02d' "$i"
            return 0
        fi
    done
    return 1
}

# Pasos pedidos: los de la línea de órdenes, o todos (desde DESDE si se indicó).
PEDIDOS=("$@")
if [[ ${#PEDIDOS[@]} -eq 0 ]]; then
    for p in "${PASOS[@]}"; do
        if [[ -n "${DESDE:-}" && -z "${ENCONTRADO:-}" && "$p" != "$DESDE" ]]; then
            continue
        fi
        ENCONTRADO=si
        PEDIDOS+=("$p")
    done
    if [[ -n "${DESDE:-}" && -z "${ENCONTRADO:-}" ]]; then
        echo "DESDE=$DESDE no es un paso. Pasos: ${PASOS[*]}" >&2
        exit 1
    fi
fi
for p in "${PEDIDOS[@]}"; do
    numero_de "$p" >/dev/null || { echo "Paso desconocido: $p. Pasos: ${PASOS[*]}" >&2; exit 1; }
done

mkdir -p "$DIRECTORIO" "$RENDIMIENTO/resultados"

# ---------------------------------------------------------------------------
# Piezas comunes
# ---------------------------------------------------------------------------
GENERADOR_CPU=()
if [[ -n "$CPUS_GENERADOR" ]]; then
    GENERADOR_CPU=(--cpuset-cpus "$CPUS_GENERADOR")
    export PG_DOCKER_OPCIONES="--cpuset-cpus $CPUS_GENERADOR"
fi

# Credenciales de la base sin leer el .env: las del propio contenedor (el dueño).
credenciales() {
    PGUSER="$(dc exec -T db printenv POSTGRES_USER | tr -d '\r')"
    PGPASSWORD="$(dc exec -T db printenv POSTGRES_PASSWORD | tr -d '\r')"
    PGDATABASE="$(dc exec -T db printenv POSTGRES_DB | tr -d '\r')"
    export PGUSER PGPASSWORD PGDATABASE PGHOST=127.0.0.1 PGPORT=55432
}

# Fija el SUT a sus CPU (idempotente). El generador queda en las otras.
aplicar_cpusets() {
    [[ -n "$CPUS_SUT" ]] || return 0
    local id
    for id in $(dc ps -q); do
        docker update --cpuset-cpus "$CPUS_SUT" "$id" >/dev/null
    done
}

esperar_aplicacion() {
    local i
    for ((i = 0; i < 90; i++)); do
        if [[ "$(curl -s -o /dev/null -w '%{http_code}' "$BASE_URL/up" || true)" == "200" ]]; then
            return 0
        fi
        sleep 2
    done
    echo "El SUT no respondió 200 en $BASE_URL/up." >&2
    return 1
}

# psql dentro del contenedor de la base, con el dueño; el SQL entra por stdin.
psql_dueno() {
    dc exec -T db sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -X -q -At -F " " -v ON_ERROR_STOP=1'
}

# Marcas de tiempo de un paso en curso.
iniciar_paso() {
    NOMBRE="$1"
    IP_MODO="$2"
    CARPETA="$DIRECTORIO/${NUM}-${NOMBRE}"
    mkdir -p "$CARPETA"
    INICIO_ISO="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    INICIO_LEGIBLE="$(date '+%H:%M:%S')"
    avance "INICIO $NOMBRE"

    local libre_mb
    libre_mb="$(df -Pm "$RENDIMIENTO" | awk 'NR==2 {print $4}')"
    if ((libre_mb < 2000)); then
        avance "AVISO $NOMBRE: quedan solo ${libre_mb} MB de disco"
    fi

    credenciales
    "$RENDIMIENTO/base-de-datos/estadisticas.sh" reiniciar >>"$CARPETA/estadisticas-reinicio.log" 2>&1 \
        || avance "AVISO $NOMBRE: no se pudieron reiniciar las estadísticas de PostgreSQL"

    SALIDA="$CARPETA/recursos.jsonl" INTERVALO=2 "$RENDIMIENTO/herramientas/muestrear-recursos.sh" \
        >"$CARPETA/muestreo.log" 2>&1 &
    MUESTREO=$!
}

# Cifra de un resumen de recursos: contenedor (patrón), columna (3 = media, 4 = p95, 5 = máx).
cpu_de() {
    awk -v patron="$2" -v col="$3" '$1 ~ patron { print $col; encontrado = 1 } END { if (!encontrado) print "-" }' "$1" | head -1
}

# Cierra el paso: detiene el muestreo, resume nginx, lee las estadísticas y escribe la ficha.
# $1 = veredicto de la prueba (k6 u otro), $2 = nota opcional.
cerrar_paso() {
    local veredicto="$1" nota="${2:-}"
    kill "$MUESTREO" 2>/dev/null || true
    wait "$MUESTREO" 2>/dev/null || true

    "$RENDIMIENTO/herramientas/muestrear-recursos.sh" --resumen "$CARPETA/recursos.jsonl" >"$CARPETA/recursos.txt" 2>&1 \
        || echo "Sin muestras de recursos (el paso duró menos que un muestreo)." >"$CARPETA/recursos.txt"

    dc logs --no-log-prefix --since "$INICIO_ISO" web >"$CARPETA/nginx.log" 2>/dev/null || true
    local resumir=(php "$RENDIMIENTO/herramientas/resumir-nginx.php")
    "${resumir[@]}" <"$CARPETA/nginx.log" >"$CARPETA/nginx.txt" || true
    "${resumir[@]}" --json <"$CARPETA/nginx.log" >"$CARPETA/nginx.json" || true
    "${resumir[@]}" --solo-paginas <"$CARPETA/nginx.log" >"$CARPETA/nginx-paginas.txt" || true
    "${resumir[@]}" --solo-paginas --json <"$CARPETA/nginx.log" >"$CARPETA/nginx-paginas.json" || true
    rm -f "$CARPETA/nginx.log"

    "$RENDIMIENTO/base-de-datos/estadisticas.sh" leer >"$CARPETA/estadisticas.txt" 2>&1 \
        || avance "AVISO $NOMBRE: no se pudieron leer las estadísticas de PostgreSQL"

    escribir_ficha "$veredicto" "$nota"
    avance "FIN $NOMBRE $veredicto"
}

# "VEREDICTO|p95|429|5xx" de un JSON de resumir-nginx.php (el veredicto puede llevar espacios).
cifras_nginx() {
    jq -r '[.veredicto, (.total.p95 // "-" | tostring), (.total["429"] // "-" | tostring), (.total["5xx"] // "-" | tostring)] | join("|")' "$1" 2>/dev/null \
        || echo "SIN DATOS|-|-|-"
}

escribir_ficha() {
    local veredicto="$1" nota="$2"
    local fin_legible total pag
    fin_legible="$(date '+%H:%M:%S')"
    total="$(cifras_nginx "$CARPETA/nginx.json")"
    pag="$(cifras_nginx "$CARPETA/nginx-paginas.json")"
    IFS='|' read -r v_total p95_total n429 n5xx <<<"$total"
    IFS='|' read -r v_pag p95_pag _ _ <<<"$pag"

    # descargas provoca 429 a propósito para comprobar los topes: su «NO CUMPLE» de nginx
    # no dice nada del RNF01. Las cifras se dejan para quien quiera verlas.
    if [[ "$NOMBRE" == *descargas ]]; then
        v_total="NO APLICA (la prueba provoca 429 a propósito)"
        v_pag="$v_total"
    fi

    local r="$CARPETA/recursos.txt" advertencia=""
    local gen_med gen_p95
    gen_med="$(cpu_de "$r" generador 3)"
    gen_p95="$(cpu_de "$r" generador 4)"
    if [[ "$gen_p95" != "-" ]] && awk -v v="$gen_p95" 'BEGIN { exit !(v > 90) }'; then
        advertencia="ADVERTENCIA: el generador pasó del 90 % de CPU (p95 $gen_p95 %): este paso puede estar midiendo al generador."
    fi

    {
        echo "== ${NUM}-${NOMBRE} =="
        echo "  inicio $INICIO_LEGIBLE, fin $fin_legible"
        echo "  modo de IP: $IP_MODO"
        echo "  prueba: $veredicto"
        echo "  nginx, todo:    $v_total (p95 ${p95_total} s, 429: ${n429}, 5xx: ${n5xx})"
        echo "  nginx, páginas: $v_pag (p95 ${p95_pag} s; el veredicto del RNF01 es el de las páginas)"
        echo "  CPU media/máx %: app $(cpu_de "$r" -app- 3)/$(cpu_de "$r" -app- 5), db $(cpu_de "$r" -db- 3)/$(cpu_de "$r" -db- 5), web $(cpu_de "$r" -web- 3)/$(cpu_de "$r" -web- 5), generador ${gen_med}/$(cpu_de "$r" generador 5)"
        [[ -z "$advertencia" ]] || echo "  $advertencia"
        [[ -z "$nota" ]] || echo "  nota: $nota"
        echo "  archivos: rendimiento/resultados/$CAMPANA/${NUM}-${NOMBRE}/"
    } >"$CARPETA/ficha.txt"
}

veredicto_k6() {
    case "$1" in
        0) echo "CUMPLE los umbrales" ;;
        99) echo "NO CUMPLE los umbrales" ;;
        *) echo "ERROR de ejecución (código $1)" ;;
    esac
}

# Corre un script k6 HTTP en un contenedor. $1 = archivo de k6/, el resto NOMBRE=valor.
correr_k6() {
    local script="$1"
    shift
    local entorno=(-e "BASE_URL=$BASE_URL" -e "IP_POR_USUARIO=$IP_MODO" -e "RESULTADOS=resultados/$CAMPANA/${NUM}-${NOMBRE}")
    local v
    for v in "$@"; do
        entorno+=(-e "$v")
    done
    K6_CODIGO=0
    docker run --rm --name laboratorio-generador --user "$(id -u):$(id -g)" --network host \
        ${GENERADOR_CPU[@]+"${GENERADOR_CPU[@]}"} \
        -v "$RENDIMIENTO:/rendimiento" -w /rendimiento "${entorno[@]}" \
        "$K6_IMAGEN" run -q "k6/$script" >"$CARPETA/k6.log" 2>&1 || K6_CODIGO=$?
}

# Paso HTTP genérico: nombre, modo de IP, script, variables...
paso_k6() {
    local nombre="$1" ip="$2" script="$3"
    shift 3
    iniciar_paso "$PREFIJO$nombre" "$ip"
    correr_k6 "$script" "$@"
    local veredicto
    veredicto="$(veredicto_k6 "$K6_CODIGO")"
    [[ "$K6_CODIGO" == 0 || "$K6_CODIGO" == 99 ]] || avance "ERROR $PREFIJO$nombre k6 salió con código $K6_CODIGO (ver k6.log)"
    cerrar_paso "$veredicto"
}

# Una pasada de k6 browser, con tope de duración. $1 = script, el resto NOMBRE=valor.
# Deja el código de salida en K6_CODIGO (124 = tope agotado).
correr_navegador() {
    local script="$1"
    shift
    local entorno=(-e "BASE_URL=$BASE_URL" -e "SALIDA=../resultados/$CAMPANA/${NUM}-${NOMBRE}")
    local v
    for v in "$@"; do
        entorno+=(-e "$v")
    done
    K6_CODIGO=0
    timeout "$NAV_LIMITE" docker run --rm --name laboratorio-generador --network host --shm-size=512m \
        --user "$(id -u):$(id -g)" -e HOME=/tmp -e K6_BROWSER_ARGS=no-sandbox "${entorno[@]}" \
        -v "$RAIZ:/repo" -w /repo/rendimiento/navegador \
        "$K6_IMAGEN_NAVEGADOR" run --quiet "$script" >>"$CARPETA/k6.log" 2>>"$CARPETA/registro.txt" || K6_CODIGO=$?
    [[ "$K6_CODIGO" != 124 ]] || docker rm -f laboratorio-generador >/dev/null 2>&1 || true
}

# Navegador (k6 browser). $1 = script, $2 = nombre, el resto NOMBRE=valor.
# web-vitals.js corre una página por proceso de k6: en la validación el navegador se colgó
# (Chromium sin responder, 0 % de CPU, sin fin) a mitad de la lista de páginas, y un solo
# proceso largo lo perdía todo. Así un cuelgue cuesta una página, que se reintenta una vez.
# Sus resúmenes quedan en resumen-<página>.txt; los .json y .txt por página y perfil, aparte.
paso_navegador() {
    local script="$1" nombre="$2"
    shift 2
    iniciar_paso "$PREFIJO$nombre" "no aplica"
    : >"$CARPETA/k6.log"
    : >"$CARPETA/registro.txt"

    local paginas=("")
    [[ "$script" != "web-vitals.js" ]] || read -ra paginas <<<"$LISTA_PAGINAS_NAV"

    local peor=0 pagina intento
    for pagina in "${paginas[@]}"; do
        for intento in 1 2; do
            if [[ -n "$pagina" ]]; then
                correr_navegador "$script" "$@" "PAGINAS=$pagina"
            else
                correr_navegador "$script" "$@"
            fi
            [[ "$K6_CODIGO" == 124 ]] || break
            avance "AVISO $PREFIJO$nombre ${pagina:-recorridos}: el navegador no terminó en $NAV_LIMITE (intento $intento)"
        done
        if [[ -n "$pagina" && -f "$DIRECTORIO/${NUM}-${NOMBRE}/resumen.txt" ]]; then
            mv "$DIRECTORIO/${NUM}-${NOMBRE}/resumen.txt" "$DIRECTORIO/${NUM}-${NOMBRE}/resumen-$pagina.txt"
        fi
        if [[ "$K6_CODIGO" == 124 ]]; then
            avance "ERROR $PREFIJO$nombre ${pagina:-recorridos}: sin resultado tras dos intentos"
            peor=124
        elif [[ "$K6_CODIGO" != 0 && "$K6_CODIGO" != 99 ]]; then
            avance "ERROR $PREFIJO$nombre ${pagina:-recorridos}: k6 browser salió con código $K6_CODIGO (ver registro.txt)"
            [[ "$peor" == 124 ]] || peor="$K6_CODIGO"
        elif [[ "$K6_CODIGO" == 99 && "$peor" == 0 ]]; then
            peor=99
        fi
    done
    cerrar_paso "$(veredicto_k6 "$peor")" "navegador sin cpuset (corre donde el sistema lo ponga); un proceso de k6 por página"
}

# ---------------------------------------------------------------------------
# Entorno e índice
# ---------------------------------------------------------------------------
escribir_entorno() {
    {
        echo "Campaña $CAMPANA, $(date '+%Y-%m-%d %H:%M:%S')"
        echo "PHP:        $(dc exec -T app php -v 2>/dev/null | head -1 || true)"
        echo "nginx:      $(dc exec -T web nginx -v 2>&1 | head -1 || true)"
        echo "PostgreSQL: $(dc exec -T db postgres --version 2>/dev/null | head -1 || true)"
        echo "k6:         $(docker run --rm "$K6_IMAGEN" version 2>/dev/null | head -1 || true)"
        echo "nproc: $(nproc); memoria: $(free -h | awk 'NR==2 {print $2}')"
        local trabajo
        trabajo="$(docker inspect -f '{{index .Config.Labels "com.docker.compose.project.working_dir"}}' "$(dc ps -q app | head -1)" 2>/dev/null || true)"
        echo "Commit del SUT: $(git -C "${trabajo:-$RAIZ}" rev-parse HEAD 2>/dev/null || echo desconocido) (${trabajo:-$RAIZ})"
        echo "Commit del arnés: $(git -C "$RAIZ" rev-parse HEAD 2>/dev/null || echo desconocido)"
        echo "Factor de volumen: $FACTOR (el último que corrió preparar; x5 usa 5)"
        echo "cpuset del SUT: ${CPUS_SUT:-sin fijar}; cpuset del generador: ${CPUS_GENERADOR:-sin fijar}"
        echo "COMPOSE: $COMPOSE"
        echo "Modo rápido: $RAPIDO"
        echo
        echo "Parámetros de la campaña:"
        echo "  pausa entre pasos ${PAUSA}s; sesiones por rol $SESIONES_POR_ROL"
        echo "  bd-referencia: escala $REF_ESCALA, ${REF_DURACION}s, clientes $REF_CLIENTES"
        echo "  bd-consultas: ${BD_DURACION_ESCENARIO}s por escenario a 10 clientes; mezcla ${BD_DURACION_MEZCLA}s con 1, 10 y 20"
        echo "  rnf01: $RNF01_PERSONAS personas en $RNF01_VENTANA (una sola IP)"
        echo "  mixta: $MIXTA_VUS VU, $MIXTA_DURACION"
        echo "  capacidad: escalones $CAP_ESCALONES r/s, $CAP_DURACION_ESCALON cada uno"
        echo "  pico y estrés: valores por defecto de sus scripts (estrés 5 → 115 r/s, +10 por minuto)"
        echo "  resistencia: $RESISTENCIA_VUS VU, $RESISTENCIA_DURACION"
        echo "  navegador: $NAV_ITERACIONES iteraciones; recorridos: $REC_ITERACIONES"
    } >"$DIRECTORIO/entorno.txt" 2>&1 || true
}

escribir_indice() {
    {
        echo "Índice de la campaña $CAMPANA"
        echo "(entorno y parámetros en entorno.txt; avance en avance.log)"
        echo
        local ficha
        for ficha in "$DIRECTORIO"/*/ficha.txt; do
            [[ -f "$ficha" ]] || continue
            cat "$ficha"
            echo
        done
        grep -E ' (ERROR|AVISO) ' "$AVANCE" 2>/dev/null | sed 's/^/! /' || true
    } >"$DIRECTORIO/indice.txt"
}

# ---------------------------------------------------------------------------
# Pasos
# ---------------------------------------------------------------------------
paso_preparar() {
    cat >&2 <<AVISO
================================================================
PREPARAR BORRA LOS VOLÚMENES DEL COMPOSE (down -v): base de datos,
documentos privados y archivos públicos. Solo en un servidor SIN
datos reales (antes de producción) o en una copia.
================================================================
AVISO
    iniciar_preparar "$PREFIJO""preparar"
    dc down -v
    dc up -d --wait db
    dc run --rm migraciones
    # shellcheck disable=SC2086
    dc up -d $SERVICIOS
    esperar_aplicacion
    aplicar_cpusets
    dc exec -T app php artisan db:seed --class=RolSeeder --force
    dc exec -T -e RENDIMIENTO_BASE_DESECHABLE=si -e "RENDIMIENTO_FACTOR=$FACTOR" app php \
        <"$RENDIMIENTO/datos/generar-volumen.php" >"$CARPETA/generar-volumen.log" 2>&1
    dc exec -T db sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -X -q -c "CREATE EXTENSION IF NOT EXISTS pg_stat_statements" -c "ANALYZE"'

    dc exec -T -e RENDIMIENTO_BASE_DESECHABLE=si -e "RENDIMIENTO_SESIONES_POR_ROL=$SESIONES_POR_ROL" app php \
        <"$RENDIMIENTO/herramientas/crear-sesiones.php" >"$CARPETA/sesiones.json" 2>"$CARPETA/sesiones.log"
    jq -e '.sesiones | length > 0' "$CARPETA/sesiones.json" >/dev/null
    cp "$CARPETA/sesiones.json" "$RENDIMIENTO/resultados/sesiones.json"
    cp "$CARPETA/sesiones.json" "$RENDIMIENTO/resultados/sesiones-navegador.json"
    # Las sesiones son llaves del sistema: no se dejan en la carpeta de la campaña.
    rm -f "$CARPETA/sesiones.json"

    psql_dueno >"$CARPETA/filas-por-tabla.txt" <<'SQL'
SELECT table_name, (xpath('/row/c/text()', query_to_xml(format('select count(*) as c from %I.%I', table_schema, table_name), false, true, '')))[1]::text::bigint AS filas
FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE' ORDER BY 2 DESC, 1;
SQL
    [[ -n "$PREFIJO" ]] || escribir_entorno
    PREPARADO="factor $FACTOR; $(awk '$1=="users" {print $2" usuarios"} $1=="solicitudes" {print $2" solicitudes"}' "$CARPETA/filas-por-tabla.txt" | tr '\n' ' ')"
    ESTADO_PREPARAR="$PREPARADO"
    escribir_ficha_preparar
    avance "FIN $NOMBRE $PREPARADO"
}

# `preparar` rehace la base: no usa estadísticas ni muestreo.
iniciar_preparar() {
    NOMBRE="$1"
    IP_MODO="no aplica"
    CARPETA="$DIRECTORIO/${NUM}-${NOMBRE}"
    mkdir -p "$CARPETA"
    INICIO_LEGIBLE="$(date '+%H:%M:%S')"
    avance "INICIO $NOMBRE"
}

escribir_ficha_preparar() {
    {
        echo "== ${NUM}-${NOMBRE} =="
        echo "  inicio $INICIO_LEGIBLE, fin $(date '+%H:%M:%S')"
        echo "  resultado: $ESTADO_PREPARAR"
        echo "  cpuset del SUT: ${CPUS_SUT:-sin fijar}"
        echo "  archivos: rendimiento/resultados/$CAMPANA/${NUM}-${NOMBRE}/ (conteo de filas por tabla en filas-por-tabla.txt)"
    } >"$CARPETA/ficha.txt"
}

paso_bd_referencia() {
    iniciar_paso "${PREFIJO}bd-referencia" "no aplica"
    local codigo=0
    ESCALA="$REF_ESCALA" DURACION="$REF_DURACION" CLIENTES="$REF_CLIENTES" RESULTADOS="$CARPETA" \
        "$RENDIMIENTO/base-de-datos/referencia-tpcb.sh" >"$CARPETA/salida.log" 2>&1 || codigo=$?
    local nota=""
    [[ "$codigo" == 0 ]] || avance "ERROR ${PREFIJO}bd-referencia pgbench salió con código $codigo"
    cerrar_paso "$([[ "$codigo" == 0 ]] && echo 'medido (sin umbral: es la línea base del hardware)' || echo "ERROR de ejecución ($codigo)")" "$nota"
}

paso_bd_planes() {
    iniciar_paso "${PREFIJO}bd-planes" "no aplica"
    local codigo=0
    MUESTRAS=3 CONSULTAS="${CONSULTAS_DIR:-$RENDIMIENTO/base-de-datos/consultas}" RESULTADOS="$CARPETA" \
        "$RENDIMIENTO/base-de-datos/explicar.sh" >"$CARPETA/salida.log" 2>&1 || codigo=$?
    [[ "$codigo" == 0 ]] || avance "ERROR ${PREFIJO}bd-planes explicar.sh salió con código $codigo"
    cerrar_paso "$([[ "$codigo" == 0 ]] && echo 'medido (ver problemas.txt)' || echo "ERROR de ejecución ($codigo)")"
}

# Cada consulta a 10 clientes y la mezcla con 1, 10 y 20 (presupuesto ≈ 10 min).
paso_bd_consultas() {
    iniciar_paso "${PREFIJO}bd-consultas" "no aplica"
    local codigo=0
    CLIENTES="10" DURACION="$BD_DURACION_ESCENARIO" MEZCLA=no RESULTADOS="$CARPETA/escenarios" \
        "$RENDIMIENTO/base-de-datos/consultas-calientes.sh" >"$CARPETA/escenarios.log" 2>&1 || codigo=$?
    CLIENTES="1 10 20" DURACION="$BD_DURACION_MEZCLA" SOLO_MEZCLA=si ESTADISTICAS=si RESULTADOS="$CARPETA/mezcla" \
        "$RENDIMIENTO/base-de-datos/consultas-calientes.sh" >"$CARPETA/mezcla.log" 2>&1 || codigo=$((codigo + $?))
    [[ "$codigo" == 0 ]] || avance "ERROR ${PREFIJO}bd-consultas consultas-calientes.sh salió con código $codigo"
    cerrar_paso "$([[ "$codigo" == 0 ]] && echo 'medido' || echo "ERROR de ejecución ($codigo)")"
}

# Trabajos por segundo del único worker de la cola (PTb-5-G de los procesos en segundo plano).
paso_cola() {
    iniciar_paso "${PREFIJO}cola" "no aplica"
    local antes_fallidos pendientes inicio fin
    antes_fallidos="$(echo "SELECT count(*) FROM failed_jobs" | psql_dueno)"
    # Un script por stdin, igual que las demás herramientas: no depende de tinker (no está en producción).
    dc exec -T app php >"$CARPETA/encolado.log" 2>&1 <<PHP
<?php
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
for (\$i = 0; \$i < $COLA_TRABAJOS; \$i++) {
    Illuminate\Support\Facades\Artisan::queue('inspire');
}
echo "Encolados: $COLA_TRABAJOS\n";
PHP
    inicio="$(date +%s.%N)"
    pendientes="$(echo "SELECT count(*) FROM jobs" | psql_dueno)"
    local limite=$((SECONDS + 600))
    while [[ "$pendientes" != "0" ]] && ((SECONDS < limite)); do
        sleep 0.5
        pendientes="$(echo "SELECT count(*) FROM jobs" | psql_dueno)"
    done
    fin="$(date +%s.%N)"
    local fallidos
    fallidos=$(($(echo "SELECT count(*) FROM failed_jobs" | psql_dueno) - antes_fallidos))
    awk -v n="$COLA_TRABAJOS" -v a="$inicio" -v b="$fin" -v p="$pendientes" -v f="$fallidos" 'BEGIN {
        t = b - a
        printf "trabajos: %d\nsegundos hasta vaciar la tabla jobs: %.2f\ntrabajos por segundo: %.1f\nquedan en jobs: %d\nnuevos en failed_jobs: %d\n", n, t, n / t, p, f
    }' >"$CARPETA/cola.txt"
    cat "$CARPETA/cola.txt" >&2
    local veredicto="medido: $(sed -n 's/^trabajos por segundo: //p' "$CARPETA/cola.txt") trabajos/s, fallidos $fallidos"
    [[ "$pendientes" == "0" ]] || veredicto="ERROR: la cola no se vació en 10 minutos (quedan $pendientes)"
    cerrar_paso "$veredicto"
}

paso_x5() {
    avance "INICIO x5 (prepara con factor 5 y repite humo, rnf01, bd-planes, mezcla y web-vitals del panel)"
    PREFIJO="x5-"
    FACTOR=5 ejecutar preparar paso_preparar
    pausa
    ejecutar humo paso_humo
    pausa
    ejecutar rnf01 paso_rnf01
    pausa
    CONSULTAS_DIR="$RENDIMIENTO/base-de-datos/consultas-x5" ejecutar bd-planes paso_bd_planes
    pausa
    ejecutar bd-mezcla paso_x5_mezcla
    pausa
    ejecutar web-vitals paso_x5_web_vitals
    PREFIJO=""
    avance "FIN x5"
}

paso_x5_mezcla() {
    iniciar_paso "${PREFIJO}bd-mezcla" "no aplica"
    local codigo=0
    CONSULTAS="$RENDIMIENTO/base-de-datos/consultas-x5" CLIENTES="1 10 20" DURACION="$BD_DURACION_MEZCLA" \
        SOLO_MEZCLA=si ESTADISTICAS=si RESULTADOS="$CARPETA/mezcla" \
        "$RENDIMIENTO/base-de-datos/consultas-calientes.sh" >"$CARPETA/mezcla.log" 2>&1 || codigo=$?
    [[ "$codigo" == 0 ]] || avance "ERROR ${PREFIJO}bd-mezcla consultas-calientes.sh salió con código $codigo"
    cerrar_paso "$([[ "$codigo" == 0 ]] && echo 'medido' || echo "ERROR de ejecución ($codigo)")"
}

paso_x5_web_vitals() {
    LISTA_PAGINAS_NAV="hoy-estudiante hoy-docente hoy-administrativo calendario bandeja tablero" \
        paso_navegador web-vitals.js web-vitals "ITERACIONES=$(rap 1 3)"
}

paso_humo() { paso_k6 humo si humo.js "REPETICIONES=$(rap 1 3)"; }
paso_rnf01() { paso_k6 rnf01 no carga-rnf01.js "PERSONAS=$RNF01_PERSONAS" "VENTANA=$RNF01_VENTANA"; }

# El modo de IP lo decide solo k6: el SUT ya confía en la red de Docker (PROXIES_DE_CONFIANZA).
paso_pico() {
    paso_k6 "$1" "$2" pico.js $(rap "VUS=300 SUBIDA=3s SOSTENIDO=10s BAJADA=3s BASE=5s RECUPERACION=10s" "")
}

paso_estres() {
    paso_k6 "$1" "$2" estres.js $(rap "TASA_INICIAL=5 PASO=40 ESCALONES=3 DURACION_ESCALON=10s" "")
}

despachar() {
    case "$1" in
        preparar) paso_preparar ;;
        bd-referencia) paso_bd_referencia ;;
        bd-planes) paso_bd_planes ;;
        bd-consultas) paso_bd_consultas ;;
        humo) paso_humo ;;
        rnf01) paso_rnf01 ;;
        mixta) paso_k6 mixta si carga-mixta.js "VUS=$MIXTA_VUS" "DURACION=$MIXTA_DURACION" "RAMPA=$(rap 5s 2m)" ;;
        capacidad) paso_k6 capacidad si capacidad.js "ESCALONES=$CAP_ESCALONES" "DURACION_ESCALON=$CAP_DURACION_ESCALON" ;;
        pico-una-ip) paso_pico pico-una-ip no ;;
        pico-ip-real) paso_pico pico-ip-real si ;;
        estres-una-ip) paso_estres estres-una-ip no ;;
        estres-ip-real) paso_estres estres-ip-real si ;;
        concurrencia) paso_k6 concurrencia si concurrencia.js "N=$(rap 5 10)" "ESPERA=$(rap 5s 10s)" ;;
        descargas) paso_k6 descargas si descargas.js "REPETICIONES=$(rap 1 3)" ;;
        cola) paso_cola ;;
        navegador) paso_navegador web-vitals.js navegador "ITERACIONES=$NAV_ITERACIONES" ;;
        recorridos) paso_navegador recorridos.js recorridos "ITERACIONES=$REC_ITERACIONES" ;;
        resistencia) paso_k6 resistencia si resistencia.js "DURACION=$RESISTENCIA_DURACION" "VUS=$RESISTENCIA_VUS" "VENTANA=$(rap 20s 5m)" ;;
    esac
}

# Corre un paso aislado: si el arnés falla se anota ERROR y la campaña sigue.
# $1 = nombre, $2 = función a ejecutar.
ejecutar() {
    local nombre="$1" funcion="$2" codigo=0
    NUM="$(numero_de "${NOMBRE_PASO_PRINCIPAL:-$nombre}")"
    # `set -e` solo vale dentro de un subshell que no esté en una condición.
    set +e
    (
        set -e
        "$funcion" "$nombre"
    )
    codigo=$?
    set -e
    if ((codigo != 0)); then
        avance "ERROR $PREFIJO$nombre el paso falló con código $codigo"
        mkdir -p "$DIRECTORIO/${NUM}-${PREFIJO}${nombre}"
        if [[ ! -f "$DIRECTORIO/${NUM}-${PREFIJO}${nombre}/ficha.txt" ]]; then
            printf '== %s-%s%s ==\n  prueba: ERROR del arnés (código %s); ver avance.log y los registros de la carpeta\n' \
                "$NUM" "$PREFIJO" "$nombre" "$codigo" >"$DIRECTORIO/${NUM}-${PREFIJO}${nombre}/ficha.txt"
        fi
    fi
    escribir_indice
}

PASOS_CORRIDOS=0
pausa() {
    if ((PASOS_CORRIDOS > 0)); then
        avance "PAUSA ${PAUSA}s (los topes por minuto se vacían)"
        sleep "$PAUSA"
    fi
    PASOS_CORRIDOS=$((PASOS_CORRIDOS + 1))
}

# ---------------------------------------------------------------------------
# Principal
# ---------------------------------------------------------------------------
PREFIJO=""
NUM=""
avance "CAMPAÑA $CAMPANA: pasos ${PEDIDOS[*]} (RAPIDO=$RAPIDO, factor $FACTOR)"

# Si el SUT ya está arriba (reanudar), se fijan sus CPU y se anota el entorno.
if [[ "${PEDIDOS[0]}" != "preparar" ]]; then
    aplicar_cpusets || true
    escribir_entorno
fi

for paso in "${PEDIDOS[@]}"; do
    pausa
    if [[ "$paso" == "x5" ]]; then
        NOMBRE_PASO_PRINCIPAL=x5
        paso_x5
        unset NOMBRE_PASO_PRINCIPAL
    else
        # La función recibe el nombre; los pasos ignoran el argumento, el número sale del paso.
        despachar_nombre="$paso"
        ejecutar "$paso" "despachar"
    fi
done

escribir_indice
avance "CAMPAÑA TERMINADA"
