#!/bin/sh
# Arranque de los contenedores de producción (app y queue).
#
# "optimize" guarda en caché la configuración, las rutas, los eventos y las
# vistas. Se hace al arrancar y no al construir la imagen porque la
# configuración depende del .env, que no está dentro de la imagen: la misma
# imagen sirve para cualquier servidor.
#
# Consecuencia para quien mantenga el sistema: después de cambiar el .env
# hay que reiniciar los contenedores para que lo lean
# (docker compose -f docker-compose.produccion.yml up -d --force-recreate app queue).
set -eu

php artisan optimize

exec "$@"
