# Imagen de la aplicación: PHP 8.3-FPM sobre Debian, igual que el servidor institucional.
FROM php:8.3-fpm-bookworm

# El usuario del contenedor se remapea al del host para que los archivos escritos
# sobre el volumen montado (storage, caché, código generado por artisan) no queden
# como root en la máquina del desarrollador.
ARG UID=1000
ARG GID=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libzip-dev \
        libicu-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
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

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-aplicacion.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

RUN groupmod --non-unique --gid "${GID}" www-data \
    && usermod --non-unique --uid "${UID}" --gid "${GID}" --home /var/www www-data \
    && mkdir -p /var/www/.composer \
    && chown -R www-data:www-data /var/www

ENV COMPOSER_HOME=/var/www/.composer

WORKDIR /var/www/html

EXPOSE 9000

CMD ["php-fpm"]
