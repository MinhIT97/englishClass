# ------------------------------------
# Giai đoạn 1: Build Vite Assets (Node)
# ------------------------------------
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# ------------------------------------
# Giai đoạn 2: Install Composer Dependencies
# ------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

# ------------------------------------
# Giai đoạn 3: Production Runtime Image (Alpine)
# ------------------------------------
FROM php:8.4-fpm-alpine AS production

# Cài đặt dependencies hệ thống cần thiết
RUN apk add --no-cache \
        bash \
        curl \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        icu-dev \
        oniguruma-dev \
        libxml2-dev \
        zip \
        unzip \
        mysql-client \
        fcgi

# Cài đặt PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
        sockets

# Cài đặt Redis extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# PHP production configuration
RUN { \
    echo "memory_limit = 256M"; \
    echo "upload_max_filesize = 100M"; \
    echo "post_max_size = 100M"; \
    echo "max_execution_time = 300"; \
    echo "expose_php = Off"; \
} > /usr/local/etc/php/conf.d/custom.ini

WORKDIR /app

# Copy toàn bộ source từ vendor stage
COPY --from=vendor --chown=www-data:www-data /app /app

# Copy Vite build assets từ frontend stage
COPY --from=frontend --chown=www-data:www-data /app/public/build /app/public/build

# Copy entrypoint
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Đảm bảo quyền sở hữu cho www-data
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["php-fpm"]
