# ------------------------------------
# 1. Stage: Build Vite Assets (Node)
# ------------------------------------
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json postcss.config.js tailwind.config.js vite.config.js vite-module-loader.js ./
RUN npm ci
COPY resources resources/
COPY public public/
RUN npm run build

# ------------------------------------
# 2. Stage: Build Composer (PHP Vendor)
# ------------------------------------
FROM composer:latest AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --ignore-platform-reqs --no-interaction --no-plugins --no-scripts --prefer-dist
COPY . .
RUN composer dump-autoload --optimize --no-dev

# ------------------------------------
# 3. Stage: Final Production Image
# ------------------------------------
FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    git curl libcurl curl-dev zip unzip \
    mariadb-client postgresql-client \
    libzip-dev libxml2-dev openssl-dev oniguruma-dev bash pkgconf && \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS && \
    docker-php-ext-configure zip && \
    docker-php-ext-install pdo pdo_mysql zip bcmath curl mbstring xml opcache pcntl && \
    apk del .build-deps

# PHP configurations
RUN echo "memory_limit = 256M" > /usr/local/etc/php/conf.d/custom.ini \
    && echo "upload_max_filesize = 100M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "post_max_size = 100M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /app

# Copy from vendor and frontend stages
COPY --from=vendor /app /app
COPY --from=frontend /app/public/build /app/public/build

# Setup entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Permissions
RUN mkdir -p storage/logs bootstrap/cache \
    && chown -R www-data:www-data /app \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 9000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
