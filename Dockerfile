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
# Front-end (Vite). A configuração do Reverb chega em tempo de execução pelo
# Laravel, então a mesma imagem serve para qualquer domínio.
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.ts tsconfig.json ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# PHP-FPM da aplicação (também usado por reverb, fila e comandos artisan).
FROM base AS prod
COPY docker/php/php.prod.ini /usr/local/etc/php/conf.d/zz-nexo-prod.ini
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --optimize --no-dev     && php artisan package:discover --ansi     && rm -f public/hot     && chown -R www-data:www-data storage bootstrap/cache     && chmod +x docker/php/entrypoint.prod.sh
USER www-data
ENTRYPOINT ["docker/php/entrypoint.prod.sh"]
CMD ["php-fpm"]

# Nginx com os arquivos públicos (CSS/JS/fontes/widget.js) embutidos.
FROM nginx:1.27-alpine AS web
COPY docker/nginx/prod.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/html/public
COPY --from=assets /app/public/build /var/www/html/public/build
