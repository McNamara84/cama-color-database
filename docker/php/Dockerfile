ARG PHP_IMAGE=php:8.5-fpm-bookworm@sha256:eec275cd99580e0a6e103dd343a7c160c86917003f6b9bba2d4c75d82419c683
FROM composer:2@sha256:ca11f643d7791ebdd90a34fc20caf11380d434007ae539a9ea75cd9212e13dca AS composer
FROM ${PHP_IMAGE} AS runtime
RUN apt-get update && apt-get install -y --no-install-recommends libicu-dev libonig-dev libzip-dev unzip git openssh-client curl \
    && docker-php-ext-install -j2 pdo_mysql mbstring intl zip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/cama.ini
WORKDIR /app
ENV COMPOSER_HOME=/tmp/cama-composer

FROM runtime AS development
RUN pecl install xdebug-3.5.3 && docker-php-ext-enable xdebug
ENV XDEBUG_MODE=off
