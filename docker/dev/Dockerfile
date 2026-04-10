FROM php:8.5-fpm-bookworm

ENV DEBIAN_FRONTEND=noninteractive

# 1. Системні бібліотеки та розширення PHP (твій код + git)
RUN apt-get update && apt-get install -y --no-install-recommends \
        libonig-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libzip-dev \
        libicu-dev \
        libsodium-dev \
        default-mysql-client \
        supervisor \
        unzip \
        curl \
        git \
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

# 2. Встановлюємо Node.js (для локального npm run dev)
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs

# 3. Беремо Composer з офіційного образу
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

COPY docker/production/worker/supervisord.conf /etc/supervisor/conf.d/worker.conf

COPY docker/dev/php-dev.ini /usr/local/etc/php/conf.d/zz-dev.ini

WORKDIR /var/www/html
