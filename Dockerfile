ARG PHP_VERSION=8.3

FROM node:22-alpine AS assets

WORKDIR /app

COPY package*.json ./
RUN npm install --ignore-scripts

COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM composer:2 AS composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

COPY . .
RUN composer dump-autoload --optimize

FROM php:${PHP_VERSION}-fpm-alpine AS production

WORKDIR /var/www/html

ENV PORT=8080

RUN apk add --no-cache \
        bash \
        freetype \
        gettext \
        libjpeg-turbo \
        libpng \
        libwebp \
        libzip \
        nginx \
        oniguruma \
        libpq \
        zip \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
        oniguruma-dev \
        postgresql-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        mbstring \
        pcntl \
        pdo \
        pdo_pgsql \
        zip \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/*

COPY --chown=www-data:www-data . .
COPY --from=composer --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

COPY nginx.conf /etc/nginx/nginx.conf.template
COPY start.sh /usr/local/bin/start.sh

RUN chmod +x /usr/local/bin/start.sh \
    && mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
        /run/nginx \
    && chown -R www-data:www-data storage bootstrap/cache public \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE ${PORT}

ENTRYPOINT ["/usr/local/bin/start.sh"]
