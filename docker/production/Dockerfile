# =============================================================================
# Stage 1: Node – compile Vite/Vue/Tailwind frontend assets
# =============================================================================
FROM node:22-alpine AS node-builder

WORKDIR /app

# Install deps first (layer-cache friendly)
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

# Copy only what Vite needs to build
COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources/ resources/
COPY public/ public/

RUN npm run build

# =============================================================================
# Stage 2: Composer – install PHP production dependencies
# =============================================================================
FROM composer:2.8 AS composer-builder

WORKDIR /app

# Install deps first (layer-cache friendly)
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --no-autoloader

# Copy full source, then optimise autoloader
COPY . .
RUN composer dump-autoload --optimize --no-dev

# =============================================================================
# Stage 3: PHP-FPM production image
# =============================================================================
FROM php:8.5-fpm-bookworm AS production

ENV DEBIAN_FRONTEND=noninteractive

# System dependencies + supervisor (used by worker container, harmless in app)
RUN apt-get update && apt-get install -y --no-install-recommends \
        libonig-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libzip-dev \
        libicu-dev \
        libsodium-dev \
        supervisor \
        unzip \
        curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        bcmath \
        gd \
        intl \
        mbstring \
        pcntl \
        sockets \
        sodium \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get autoremove -y \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/pear /tmp/*

# PHP & FPM configuration
COPY docker/production/php.ini     "$PHP_INI_DIR/conf.d/99-production.ini"
COPY docker/production/php-fpm.conf /usr/local/etc/php-fpm.d/zz-docker-app.conf

# Supervisor config for queue worker (only active when worker container CMD uses it)
COPY docker/production/worker/supervisord.conf /etc/supervisor/conf.d/worker.conf

WORKDIR /var/www/html

# Copy application code (PHP deps from composer stage, Vite assets from node stage)
COPY --from=composer-builder --chown=www-data:www-data /app .
COPY --from=node-builder      --chown=www-data:www-data /app/public/build public/build

# Pre-create writable directories so named volumes are seeded with correct structure
RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY --chmod=755 docker/production/entrypoint.sh /usr/local/bin/docker-entrypoint.sh

USER www-data
EXPOSE 9000
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]

# =============================================================================
# Stage 4: Nginx webserver (thin image – only public assets + config)
# =============================================================================
FROM nginx:1.27-alpine AS webserver

COPY docker/production/nginx/default.conf /etc/nginx/conf.d/default.conf

# Copy pre-built public dir from PHP stage (Vite manifest + compiled assets)
COPY --from=production /var/www/html/public /var/www/html/public

EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
