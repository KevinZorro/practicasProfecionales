#!/bin/sh
# Arranque del servicio "copias": programa la copia diaria y se queda
# esperando. La copia en sí está en hacer-copia.sh.
set -eu

: "${COPIAS_HORA:=2}"
: "${COPIAS_MINUTO:=30}"
: "${COPIAS_RETENCION_DIAS:=14}"
export COPIAS_HORA COPIAS_MINUTO COPIAS_RETENCION_DIAS

# crond no pasa a sus tareas las variables del contenedor, y hacer-copia.sh
# necesita las de PostgreSQL (PGHOST, PGPASSWORD...). Se guardan en un
# archivo que solo lee root, con las comillas que pone "export -p", para que
# una contraseña con espacios o comillas no se rompa.
export -p | grep -E "^export (PG[A-Z]+|COPIAS_[A-Z_]+|TZ)=" > /etc/copias.env
chmod 600 /etc/copias.env

# La salida va a la del proceso 1 para que se vea con
# "docker compose logs copias".
echo "${COPIAS_MINUTO} ${COPIAS_HORA} * * * . /etc/copias.env && /scripts/hacer-copia.sh > /proc/1/fd/1 2>&1" > /etc/crontabs/root

echo "Copia diaria programada a las ${COPIAS_HORA}:$(printf '%02d' "${COPIAS_MINUTO}"), se guardan ${COPIAS_RETENCION_DIAS} días."

exec crond -f -l 8
