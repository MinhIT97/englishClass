# ------------------------------------
# Stage 1: Build Vite Assets (Node)
# ------------------------------------
FROM node:20-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
# Copy config files nếu có
COPY postcss.config.js tailwind.config.js vite.config.js vite-module-loader.js ./

RUN npm ci --frozen-lockfile

COPY resources/ resources/
COPY Modules/ Modules/
COPY public/ public/

RUN npm run build

# ------------------------------------
# Stage 2: Install Composer Dependencies
# ------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

# Cài đặt không có dev, không chạy scripts để tránh lỗi thiếu .env
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# Copy toàn bộ source code vào
COPY . .

# Chạy lại dump-autoload sau khi có đủ code
RUN composer dump-autoload --no-dev --optimize --no-scripts

# ------------------------------------
# Stage 3: Production Runtime Image
# ------------------------------------
FROM php:8.4-fpm-alpine AS production

# Cài dependencies hệ thống
RUN apk add --no-cache \
        bash \
        curl \
        libcurl \
        curl-dev \
        git \
        zip \
        unzip \
        mariadb-client \
        libzip-dev \
        libxml2-dev \
        oniguruma-dev \
        pkgconf \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        openssl-dev \
        linux-headers \
    && docker-php-ext-configure zip \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        zip \
        bcmath \
        curl \
        mbstring \
        xml \
        opcache \
        pcntl \
        sockets \
    && apk del .build-deps

# PHP production configuration
RUN { \
    echo "memory_limit = 256M"; \
    echo "upload_max_filesize = 100M"; \
    echo "post_max_size = 100M"; \
    echo "max_execution_time = 300"; \
    echo "expose_php = Off"; \
} > /usr/local/etc/php/conf.d/custom.ini

# OPcache configuration
RUN { \
    echo "opcache.enable=1"; \
    echo "opcache.memory_consumption=256"; \
    echo "opcache.interned_strings_buffer=16"; \
    echo "opcache.max_accelerated_files=20000"; \
    echo "opcache.revalidate_freq=0"; \
    echo "opcache.validate_timestamps=0"; \
} > /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /app

# Copy toàn bộ source từ vendor stage (đã có code + vendor/)
COPY --from=vendor --chown=www-data:www-data /app /app

# Copy Vite build assets từ frontend stage
COPY --from=frontend --chown=www-data:www-data /app/public/build /app/public/build

# Copy entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Tạo thư mục cần thiết và set quyền
RUN mkdir -p \
        storage/app/public \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data /app \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 9000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
