#!/bin/sh
# Copia de seguridad: la base de datos, las imágenes del contenido público y
# los documentos privados (el formato de confidencialidad firmado).
#
# Cada copia es una carpeta con la fecha y la hora, por ejemplo
# /copias/2026-10-01_0230, con tres archivos y sus sumas SHA-256. Se
# restaura con restaurar-copia.sh.
#
# Se puede lanzar a mano en cualquier momento:
#   docker compose -f docker-compose.produccion.yml exec copias /scripts/hacer-copia.sh
set -eu

: "${COPIAS_RETENCION_DIAS:=14}"

# Los documentos privados tienen datos personales: nadie más que root lee
# las copias.
umask 077

marca=$(date +%Y-%m-%d_%H%M)
destino="/copias/${marca}"
en_curso="/copias/.en-curso-${marca}"

# Se escribe en una carpeta oculta y se renombra al final: una copia que se
# cortó a la mitad (disco lleno, contenedor reiniciado) nunca queda con
# nombre de copia buena.
rm -rf "${en_curso}"
mkdir -p "${en_curso}"

# Formato "custom" de pg_dump: comprimido y restaurable con pg_restore,
# incluso tabla por tabla si hiciera falta.
pg_dump --format=custom --file="${en_curso}/base-de-datos.dump"

tar -czf "${en_curso}/archivos-publicos.tar.gz" -C /datos/archivos-publicos .
tar -czf "${en_curso}/archivos-privados.tar.gz" -C /datos/archivos-privados .

(cd "${en_curso}" && sha256sum base-de-datos.dump archivos-publicos.tar.gz archivos-privados.tar.gz > SHA256SUMS)

mv "${en_curso}" "${destino}"

# Las copias más viejas que la retención se borran. Las de nombre de copia
# buena solamente: nunca se toca otra cosa que haya en el directorio.
find /copias -mindepth 1 -maxdepth 1 -type d -name '20??-??-??_????' -mtime +"${COPIAS_RETENCION_DIAS}" -exec rm -rf {} +
# Y los restos de copias que se cortaron hace más de un día.
find /copias -mindepth 1 -maxdepth 1 -type d -name '.en-curso-*' -mtime +1 -exec rm -rf {} +

echo "Copia terminada: ${destino} ($(du -sh "${destino}" | cut -f1))"
