# NEXO Chat — imagem PHP 8.4 (FPM) usada pela aplicação, filas, Reverb e testes.
FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache \
        bash git unzip icu-libs libzip libpq libpng freetype libjpeg-turbo \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS icu-dev libzip-dev postgresql-dev libpng-dev freetype-dev libjpeg-turbo-dev linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql intl zip pcntl bcmath gd opcache exif \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-nexo.ini

WORKDIR /var/www/html

# ---------- Desenvolvimento ----------
FROM base AS dev
ARG UID=1000
ARG GID=1000
RUN addgroup -g ${GID} nexo && adduser -D -u ${UID} -G nexo nexo
USER nexo

# ---------- Produção ----------
FROM base AS prod
COPY --chown=www-data:www-data . /var/www/html
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && php artisan storage:link || true
USER www-data
