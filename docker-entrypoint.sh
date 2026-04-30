#!/bin/bash
set -e

# Tự động tối ưu hóa Laravel mỗi lần khởi động
echo "Optimizing Laravel..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Tự động chạy Migration nếu là container 'app'
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Syncing database schema..."
    php artisan migrate --force
fi

# Thực thi lệnh chính của container (php-fpm, queue, hoặc reverb)
exec "$@"
