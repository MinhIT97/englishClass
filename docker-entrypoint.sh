#!/bin/bash
set -e

# Tự động tối ưu hóa Laravel mỗi lần khởi động
echo "Optimizing Laravel..."
php artisan config:clear
php artisan config:cache || true
php artisan route:clear
php artisan route:cache || true
php artisan view:clear
php artisan view:cache || true
php artisan event:clear || true

# Tự động chạy Migration nếu là container 'app'
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Syncing database schema..."
    php artisan migrate --force
fi

# Thực thi lệnh chính của container (php-fpm, queue, hoặc reverb)
exec "$@"
