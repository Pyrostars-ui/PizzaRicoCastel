# syntax=docker/dockerfile:1

ARG FRANKENPHP_VERSION=1
ARG PHP_VERSION=8.4
ARG COMPOSER_VERSION=2

# -----------------------------------------------------------------------------
# Stage 1 : Image officielle FrankenPHP (PHP 8.4 / 8.5 en support actif)
# -----------------------------------------------------------------------------
FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-php${PHP_VERSION} AS frankenphp_upstream
FROM composer:${COMPOSER_VERSION} AS composer_upstream

# -----------------------------------------------------------------------------
# Stage 2 : Socle commun (Extensions PHP, Composer, Caddyfile Worker Mode)
# -----------------------------------------------------------------------------
FROM frankenphp_upstream AS frankenphp_base

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    PHP_INI_SCAN_DIR=":$PHP_INI_DIR/app.conf.d"

RUN install-php-extensions \
    pdo_pgsql \
    intl \
    zip \
    apcu \
    opcache \
    pcntl \
    gd \
    exif

COPY --from=composer_upstream /usr/bin/composer /usr/local/bin/composer

COPY docker/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/Caddyfile /etc/caddy/Caddyfile

# -----------------------------------------------------------------------------
# Stage 3 : Environnement de Développement (montage volume & outils QA)
# -----------------------------------------------------------------------------
FROM frankenphp_base AS frankenphp_dev

ENV APP_ENV=dev \
    XDEBUG_MODE=off

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# -----------------------------------------------------------------------------
# Stage 4 : Environnement de Production (autonome, optimisé, sans dev-deps)
# -----------------------------------------------------------------------------
FROM frankenphp_base AS frankenphp_prod

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    APP_RUNTIME="Symfony\\Component\\Runtime\\SymfonyRuntime" \
    MAX_REQUESTS=500

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --link composer.* symfony.* ./
RUN set -eux; \
    composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

COPY --link . ./

RUN set -eux; \
    mkdir -p var/cache var/log public/assets public/images public/media; \
    composer dump-autoload --classmap-authoritative --no-dev; \
    composer dump-env prod; \
    php bin/console asset-map:compile --env=prod --no-debug || true; \
    chmod +x bin/console; \
    sync

