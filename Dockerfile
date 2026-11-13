# syntax=docker/dockerfile:1

# Etapa 1: dependencias PHP (composer) con cache de vendors
FROM php:8.5.12-cli-bookworm AS vendor
WORKDIR /app

RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt,sharing=locked \
    set -eux; \
    rm -f /etc/apt/apt.conf.d/docker-clean; \
    echo 'Binary::apt::APT::Keep-Downloaded-Packages "true";' > /etc/apt/apt.conf.d/keep-cache; \
    apt-get update; \
    apt-get install -y --no-install-recommends git unzip curl

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    composer install \
    --no-interaction \
    --prefer-dist \
    --no-scripts \
    --no-dev \
    --optimize-autoloader \
    --ignore-platform-req=ext-*

# Etapa 2: assets del frontend (Vite). Las variables VITE_* se fijan en tiempo de build.
FROM node:22-bookworm-slim AS assets
WORKDIR /app
ARG VITE_THEME_LAYOUT=admin-sidebar
ARG VITE_THEME_PALETTE=light-soft
ARG VITE_THEME_COLOR_MODE=auto
ARG VITE_APP_NAME=Plataforma
ARG VITE_APP_URL=
ENV VITE_THEME_LAYOUT=${VITE_THEME_LAYOUT} \
    VITE_THEME_PALETTE=${VITE_THEME_PALETTE} \
    VITE_THEME_COLOR_MODE=${VITE_THEME_COLOR_MODE} \
    VITE_APP_NAME=${VITE_APP_NAME} \
    VITE_APP_URL=${VITE_APP_URL} \
    PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1
COPY package.json package-lock.json ./
RUN --mount=type=cache,target=/root/.npm npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# Etapa 3: runtime nginx + php-fpm
FROM php:8.5.12-fpm-bookworm AS runtime
ARG WWWUSER=1000
ARG WWWGROUP=1000

RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt,sharing=locked \
    set -eux; \
    rm -f /etc/apt/apt.conf.d/docker-clean; \
    echo 'Binary::apt::APT::Keep-Downloaded-Packages "true";' > /etc/apt/apt.conf.d/keep-cache; \
    export MAKEFLAGS="-j$(nproc)"; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
    procps nginx supervisor bash curl git gosu \
    libicu-dev libzip-dev zlib1g-dev libssl-dev libpq-dev \
    libonig-dev libonig5 \
    libfreetype6-dev libjpeg62-turbo-dev libpng-dev \
    $PHPIZE_DEPS; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j$(nproc) bcmath pdo pdo_mysql pdo_pgsql intl zip gd mbstring; \
    apt-get purge -y --auto-remove libonig-dev $PHPIZE_DEPS

# Composer disponible en runtime para tareas dentro del contenedor
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Copiar código
WORKDIR /var/www/html
COPY . /var/www/html
COPY --from=vendor /app/vendor /var/www/html/vendor
COPY --from=assets /app/public/build /var/www/html/public/build

RUN rm -f /var/www/html/bootstrap/cache/*.php

# Config nginx y php-fpm
COPY deploy/nginx.conf /etc/nginx/nginx.conf
COPY deploy/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY deploy/php.ini /usr/local/etc/php/conf.d/timeout.ini
COPY deploy/supervisord.conf /etc/supervisord.conf
COPY deploy/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Permisos básicos
RUN set -eux; \
    case "${WWWGROUP}" in \
        ''|*[!0-9]*) WWWGROUP=1000 ;; \
    esac; \
    case "${WWWUSER}" in \
        ''|*[!0-9]*) WWWUSER=1000 ;; \
    esac; \
    if getent group laravel > /dev/null 2>&1; then \
        group_name=laravel; \
    else \
        if getent group "${WWWGROUP}" > /dev/null 2>&1; then \
            groupadd -o -g "${WWWGROUP}" laravel; \
        else \
            groupadd -g "${WWWGROUP}" laravel; \
        fi; \
        group_name=laravel; \
    fi; \
    useradd -u "${WWWUSER}" -g "${group_name}" -s /bin/bash -m laravel; \
    mkdir -p /run/nginx /var/lib/nginx /var/log/nginx /etc/supervisor/conf.d; \
    chown -R "${WWWUSER}:${WWWGROUP}" /var/www/html /run/nginx /var/lib/nginx /var/log/nginx /etc/supervisor/conf.d

# Variables de entorno por defecto
ENV LOG_CHANNEL=app \
    LOG_LEVEL=info \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0     PHP_FPM_MAX_CHILDREN=50     CONTAINER_ROLE=all

# Cloud Run provee $PORT; exponemos 8080 por convención
ENV PORT=8080
EXPOSE 8080

USER root

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
