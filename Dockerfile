# --- Giai đoạn 1: Build Assets (Vite/NPM) ---
FROM node:18-alpine AS asset-builder
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# --- Giai đoạn 2: Ứng dụng chính (PHP) ---
FROM php:8.4-fpm

# Cài đặt system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libicu-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libfcgi-bin

# Cài đặt PHP extensions (bao gồm Redis cho Reverb/Queue)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql mbstring exif pcntl bcmath gd zip intl

RUN pecl install redis && docker-php-ext-enable redis

# Copy Composer từ image chính thức
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy toàn bộ code vào container
COPY . /app

# Copy assets đã build từ Giai đoạn 1 sang
COPY --from=asset-builder /app/public/build /app/public/build

# Tự động cài đặt thư viện PHP (Optimized cho Production)
RUN composer install --no-interaction --optimize-autoloader --no-dev

# Phân quyền tự động cho các thư mục cache/storage
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

# Cài đặt Entrypoint
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Sử dụng user www-data để bảo mật (Optional, but entrypoint needs root for some tasks)
# USER www-data

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["php-fpm"]
