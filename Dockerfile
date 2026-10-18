# ==============================================================================
# Stage 1: PHP dependencies (cached unless composer.json/lock change)
# ==============================================================================
FROM composer:2.8 AS vendor
WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader --ignore-platform-reqs

# ==============================================================================
# Stage 2: Build Frontend Assets (Vite)
# ==============================================================================
FROM node:22-alpine AS frontend-builder
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts

# Tailwind scans views, app/ (theme classes in models) and the pagination views in vendor/
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
COPY app ./app
COPY --from=vendor /app/vendor/laravel/framework/src/Illuminate/Pagination/resources/views ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

# ==============================================================================
# Stage 3: Production PHP-FPM + Nginx Application Container
# ==============================================================================
FROM php:8.4-fpm-alpine

WORKDIR /var/www/html

# Runtime system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    postgresql-libs \
    libzip \
    icu-libs \
    libpng \
    libjpeg-turbo \
    freetype \
    tzdata

# PHP extensions (build deps are removed afterwards to keep the image small)
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
        postgresql-dev libzip-dev icu-dev libpng-dev libjpeg-turbo-dev freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql bcmath intl zip gd opcache pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# PHP, nginx & process manager configuration
COPY deployment/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY deployment/php/custom.ini /usr/local/etc/php/conf.d/custom.ini
COPY deployment/nginx/racunin-security-headers.conf /etc/nginx/snippets/racunin-security-headers.conf
COPY deployment/docker/nginx.conf /etc/nginx/http.d/default.conf
COPY deployment/docker/supervisord.conf /etc/supervisord.conf
RUN mkdir -p /run/nginx

# Application source (.dockerignore keeps .env, vendor, node_modules & tests out)
COPY . /var/www/html
COPY --from=vendor /app/vendor /var/www/html/vendor
COPY --from=frontend-builder /app/public/build /var/www/html/public/build

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY deployment/docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD wget -qO- http://127.0.0.1/up > /dev/null || exit 1

ENTRYPOINT ["entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
