#!/bin/sh
# Restaura una copia hecha por hacer-copia.sh. Borra lo que hay ahora.
#
# Antes: parar la aplicación, para que nadie escriba mientras tanto.
#   docker compose -f docker-compose.produccion.yml stop app queue programador web
# Restaurar (el argumento es el nombre de la carpeta de la copia):
#   docker compose -f docker-compose.produccion.yml run --rm restauracion 2026-10-01_0230
# Después:
#   docker compose -f docker-compose.produccion.yml start app queue programador web
set -eu

if [ "$#" -ne 1 ]; then
    echo "Uso: restaurar-copia.sh <carpeta de la copia, por ejemplo 2026-10-01_0230>" >&2
    echo "Copias disponibles:" >&2
    ls -1 /copias | grep -E '^20..-..-.._....$' >&2 || echo "  (ninguna)" >&2
    exit 1
fi

copia="/copias/$1"

if [ ! -d "${copia}" ]; then
    echo "No existe la copia ${copia}." >&2
    exit 1
fi

# Una copia dañada no se restaura: se comprueba antes de borrar nada.
(cd "${copia}" && sha256sum -c SHA256SUMS)

echo "Restaurando la base de datos..."
# --clean --if-exists borra cada objeto antes de recrearlo. --single-transaction:
# o se restaura todo, o no cambia nada.
pg_restore --clean --if-exists --no-owner --single-transaction --dbname="${PGDATABASE}" "${copia}/base-de-datos.dump"

echo "Restaurando las imágenes del contenido público..."
find /datos/archivos-publicos -mindepth 1 -maxdepth 1 -exec rm -rf {} +
tar -xzf "${copia}/archivos-publicos.tar.gz" -C /datos/archivos-publicos

echo "Restaurando los documentos privados..."
find /datos/archivos-privados -mindepth 1 -maxdepth 1 -exec rm -rf {} +
tar -xzf "${copia}/archivos-privados.tar.gz" -C /datos/archivos-privados

# tar conserva los dueños numéricos de la copia, que son los de www-data en
# la imagen de producción. Se fijan de todos modos por si la copia viene de
# otra máquina.
chown -R 33:33 /datos/archivos-publicos /datos/archivos-privados

echo "Copia ${1} restaurada. Arranca de nuevo la aplicación."
