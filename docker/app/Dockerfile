# =============================================================================
# Unified Dockerfile — single image, three CMDs (web/worker/scheduler).
#
# Local dev and production run the SAME image. Local mounts source over
# /var/www/html so file edits are live; production uses what's baked in.
#
# Web container runs FrankenPHP in worker mode (replaces nginx + php-fpm in
# the old setup). Worker container reuses the same image with supervisord
# managing per-queue workers. Scheduler container also reuses the image with
# `schedule:work` as CMD.
# =============================================================================

# -----------------------------------------------------------------------------
# Stage 1: Composer — production PHP dependencies
# -----------------------------------------------------------------------------
FROM composer:2.8 AS composer-deps

WORKDIR /app

# Lock-file first so the dep install layer caches across source-only changes.
# --ignore-platform-reqs because the composer:2.8 base lacks ext-pcntl (only
# needed at runtime, not during dependency resolution). The runtime image
# has all required extensions installed.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-interaction \
        --prefer-dist \
        --no-autoloader \
        --ignore-platform-reqs

# Then full source for autoload optimization.
COPY . .

# Clear stale Laravel package + config caches from the host before regen.
# Without this, bootstrap/cache/packages.php carries dev-only service
# providers (e.g. laravel/pail) baked in locally, then Octane fails at
# boot because those packages aren't in vendor/ in the --no-dev image.
# The fresh `package:discover` (run by the post-autoload-dump composer
# script) regenerates packages.php from the actual installed vendor tree.
RUN rm -f bootstrap/cache/*.php

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# -----------------------------------------------------------------------------
# Stage 2: Node — build Vite frontend assets (public/build/*)
# -----------------------------------------------------------------------------
FROM node:22-alpine AS node-builder

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources/ resources/
COPY public/ public/

ENV NODE_OPTIONS="--max-old-space-size=512"
RUN npm run build

# -----------------------------------------------------------------------------
# Stage 3: FrankenPHP runtime
# -----------------------------------------------------------------------------
# Latest 1.x with PHP 8.4 (matches composer.json "php": "^8.4"). The base
# image already bundles the FrankenPHP server binary; we just add PHP
# extensions and the app.
FROM dunglas/frankenphp:1.8-php8.4-bookworm AS app

ENV DEBIAN_FRONTEND=noninteractive
ENV APP_ROOT=/var/www/html
ENV SERVER_NAME=":80"

# install-php-extensions is the recommended ext installer for FrankenPHP/
# dunglas images. Idempotent and handles configure flags automatically.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libonig-dev libpng-dev libjpeg-dev libfreetype6-dev libzip-dev \
        libicu-dev libsodium-dev default-mysql-client supervisor unzip curl \
    && install-php-extensions \
        pdo_mysql \
        bcmath \
        gd \
        intl \
        mbstring \
        pcntl \
        sockets \
        sodium \
        zip \
        redis \
        opcache \
    && apt-get autoremove -y && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/pear /tmp/*

# Optimized PHP config: opcache preload for Octane, sane memory limits.
COPY docker/app/php.ini /usr/local/etc/php/conf.d/99-app.ini

# FrankenPHP server config — Caddy syntax, worker-mode Octane.
COPY docker/app/Caddyfile /etc/frankenphp/Caddyfile

# Supervisord configs — one per worker pool. entrypoint.sh dispatches the
# right file based on the CMD argument (worker-default / worker-sync /
# worker-ai). Web + scheduler don't use supervisord.
COPY docker/app/supervisord-default.conf /etc/supervisor/conf.d/default.conf
COPY docker/app/supervisord-sync.conf    /etc/supervisor/conf.d/sync.conf
COPY docker/app/supervisord-ai.conf      /etc/supervisor/conf.d/ai.conf

WORKDIR $APP_ROOT

# Copy app + vendor + built Vite assets.
COPY --from=composer-deps --chown=www-data:www-data /app .
COPY --from=node-builder  --chown=www-data:www-data /app/public/build public/build

# Pre-create writable dirs so Octane workers can write logs/cache from t=0.
RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Bake route + view caches at image build — these are env-independent so
# safe to freeze. config:cache is NOT baked here (it would freeze build-time
# env values); it runs at entrypoint for prod-like environments where the
# pod's env from k8s Secret is the source of truth. Saves ~300ms cold start.
RUN php artisan route:cache && php artisan view:cache

COPY --chmod=755 docker/app/entrypoint.sh /usr/local/bin/entrypoint.sh

# FrankenPHP listens on 80 (HTTP) and 443 (HTTPS auto via Caddy ACME).
# In k8s we'll terminate TLS at Ingress and only expose 80 inside.
EXPOSE 80 443

# Run as a non-root user. FrankenPHP can bind <1024 ports without root
# thanks to the cap_net_bind_service capability baked into the base image.
USER www-data

ENTRYPOINT ["entrypoint.sh"]
CMD ["web"]
