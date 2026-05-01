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
    git curl libpng-dev libonig-dev libxml2-dev zip unzip libzip-dev

# Cài đặt PHP extensions (bao gồm Redis cho Reverb/Queue)
RUN docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip
RUN pecl install redis && docker-php-ext-enable redis

# Copy Composer từ image chính thức
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy toàn bộ code vào container
COPY . /var/www

# Copy assets đã build từ Giai đoạn 1 sang
COPY --from=asset-builder /app/public/build /var/www/public/build

# Tự động cài đặt thư viện PHP (Optimized cho Production) với debug
RUN composer install --no-interaction --optimize-autoloader --no-dev -vvv

# Phân quyền tự động cho các thư mục cache/storage
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
RUN chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Cài đặt Entrypoint
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
