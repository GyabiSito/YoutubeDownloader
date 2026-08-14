FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY resources ./resources
COPY vite.config.js ./

RUN npm run build


FROM denoland/deno:bin-2.9.4 AS deno


FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
COPY artisan ./
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY routes ./routes

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader


FROM php:8.4-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ffmpeg \
        libicu-dev \
        libonig-dev \
        libzip-dev \
        python3-pip \
    && docker-php-ext-install intl mbstring opcache zip \
    && pip3 install \
        --break-system-packages \
        --no-cache-dir \
        --pre \
        "yt-dlp[default]" \
        "bgutil-ytdlp-pot-provider==1.3.1" \
    && a2enmod rewrite \
    && sed -ri \
        's!DocumentRoot /var/www/html!DocumentRoot /var/www/html/public!g' \
        /etc/apache2/sites-available/000-default.conf \
    && sed -ri \
        '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' \
        /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=deno /deno /usr/local/bin/deno
COPY --from=vendor /app ./

COPY public ./public
COPY resources/views ./resources/views

COPY --from=assets /app/public/build ./public/build

RUN mkdir -p \
        storage/app/downloads \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && chown -R www-data:www-data \
        storage \
        bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    SESSION_DRIVER=cookie \
    CACHE_STORE=file \
    QUEUE_CONNECTION=sync \
    YT_DLP_BINARY=yt-dlp \
    FFMPEG_BINARY=ffmpeg \
    DENO_BINARY=deno

EXPOSE 80
