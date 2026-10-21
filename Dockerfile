# --- Frontend build -----------------------------------------------------------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# --- PHP dependencies ---------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs

# --- nginx with static files (docker compose service "web") -----------------
FROM nginx:1.27-alpine AS web
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/html/public
COPY --from=assets /app/public/build /var/www/html/public/build

# --- PHP runtime (default target) ----------------------------------------------
FROM php:8.3-fpm-alpine AS app

RUN apk add --no-cache libpq-dev libzip-dev gmp-dev icu-dev \
    && docker-php-ext-install pdo_pgsql zip gmp bcmath intl opcache pcntl

COPY docker/php.ini /usr/local/etc/php/conf.d/familyhub.ini

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build
COPY --from=vendor /usr/bin/composer /usr/bin/composer

RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
CMD ["php-fpm"]
