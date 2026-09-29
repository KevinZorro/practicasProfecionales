# Imágenes de la aplicación: PHP 8.3-FPM sobre Debian 12 (bookworm).
#
# El servidor institucional es Debian 13 (trixie), pero eso es el anfitrión:
# dentro del contenedor manda esta imagen. Lo que sí tiene que coincidir con
# producción es la versión de PHP, 8.3, que es también la que fija
# config.platform.php en composer.json.
#
# Un solo archivo, varias etapas:
#
#   base          PHP con las extensiones. La comparten las demás.
#   desarrollo    la de docker-compose.yml: el código llega por un volumen.
#   dependencias  vendor/ de producción, sin las de desarrollo.
#   assets        CSS y JS compilados con Vite.
#   produccion    la aplicación entera dentro de la imagen, lista para correr.
#   web           nginx con los archivos de public/ de la de producción.
#
# docker-compose.yml construye "desarrollo"; docker-compose.produccion.yml,
# "produccion" y "web".

# ---------------------------------------------------------------------------
FROM php:8.3-fpm-bookworm AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libzip-dev \
        libicu-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        pgsql \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html

EXPOSE 9000

# ---------------------------------------------------------------------------
FROM base AS desarrollo

# El usuario del contenedor se remapea al del host para que los archivos escritos
# sobre el volumen montado (storage, caché, código generado por artisan) no queden
# como root en la máquina del desarrollador.
ARG UID=1000
ARG GID=1000

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-aplicacion.ini

# storage/app/public se crea aquí, con dueño www-data, aunque el código llegue
# por el volumen montado: docker-compose monta encima un volumen con nombre
# para las imágenes públicas, y Docker inicializa un volumen vacío con el
# dueño de la carpeta de la imagen. Sin esto quedaría de root y la
# aplicación no podría guardar ni una foto.
RUN groupmod --non-unique --gid "${GID}" www-data \
    && usermod --non-unique --uid "${UID}" --gid "${GID}" --home /var/www www-data \
    && mkdir -p /var/www/.composer /var/www/html/storage/app/public \
    && chown -R www-data:www-data /var/www

ENV COMPOSER_HOME=/var/www/.composer

CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# Solo composer.json y composer.lock: mientras no cambien, Docker reutiliza
# esta capa y no vuelve a descargar las dependencias en cada construcción.
FROM base AS dependencias

COPY composer.json composer.lock ./

RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

# ---------------------------------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY . .
# Tailwind lee las vistas de paginación de Laravel y de Livewire desde
# vendor/ (ver "content" en tailwind.config.js).
COPY --from=dependencias /var/www/html/vendor ./vendor

RUN npm run build

# ---------------------------------------------------------------------------
FROM base AS produccion

COPY docker/php/php-produccion.ini /usr/local/etc/php/conf.d/99-aplicacion.ini

COPY --chown=www-data:www-data . .
COPY --from=dependencias --chown=www-data:www-data /var/www/html/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

# Las carpetas de storage se crean antes que nada: .dockerignore las deja
# fuera de la imagen y los scripts de composer arrancan Laravel, que sin
# storage/framework/views no arranca ("Please provide a valid cache path").
# storage/app/public y storage/app/private quedan con dueño www-data por la
# misma razón que en desarrollo: son los puntos de montaje de los volúmenes.
#
# El autoload optimizado dispara los scripts de composer.json: package:discover
# y filament:upgrade, que publica los assets de Filament en public/.
#
# storage:link deja el enlace public/storage apuntando a
# /var/www/html/storage/app/public, que en producción es el volumen de las
# imágenes: el contenedor web lo monta en esa misma ruta.
RUN mkdir -p storage/app/public storage/app/private storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --optimize --no-dev --no-interaction \
    && php artisan storage:link \
    && chown -R www-data:www-data storage bootstrap/cache public

COPY docker/produccion/arrancar.sh /usr/local/bin/arrancar

USER www-data

ENTRYPOINT ["arrancar"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
FROM nginx:1.27-alpine AS web

COPY docker/produccion/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=produccion /var/www/html/public /var/www/html/public
