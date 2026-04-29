FROM php:8.4-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libcurl \
    curl-dev \
    zip \
    unzip \
    mariadb-client \
    postgresql-client \
    libzip-dev \
    libxml2-dev \
    openssl-dev \
    oniguruma-dev \
    bash \
    pkgconf

# Install PHP extensions
RUN apk add --no-cache --virtual .phpize-deps $PHPIZE_DEPS && \
    docker-php-ext-configure zip && \
    docker-php-ext-install \
    pdo \
    pdo_mysql \
    zip \
    bcmath \
    curl \
    mbstring \
    xml \
    && apk del .phpize-deps
# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Install Node.js 20.x
RUN apk add --no-cache nodejs npm

# Set PHP configurations for production
RUN echo "memory_limit = 256M" > /usr/local/etc/php/conf.d/custom.ini \
    && echo "upload_max_filesize = 100M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "post_max_size = 100M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/custom.ini

# Set working directory
WORKDIR /app

# Copy composer files first for caching
COPY composer.json composer.lock ./

# Install PHP dependencies early for build cache
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --prefer-dist \
    --no-progress \
    --no-interaction \
    --no-scripts

# Copy application files
COPY . /app

# Install Node dependencies and build assets
RUN npm ci && npm run build

# Create storage directories and set permissions
RUN mkdir -p storage/logs \
    && chown -R www-data:www-data /app \
    && chmod -R 755 storage bootstrap/cache

# Expose port
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm"]
