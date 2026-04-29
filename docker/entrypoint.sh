#!/bin/bash
set -e

echo "======================================"
echo "🚀  English Class — Starting up..."
echo "======================================"

# -------------------------------------------------------
# 1. Copy public assets vào named volume cho Nginx đọc
# -------------------------------------------------------
echo "📂 Syncing public files for Nginx..."
mkdir -p /app/public_shared
cp -a /app/public/. /app/public_shared/

# -------------------------------------------------------
# 2. Chờ MySQL sẵn sàng (dùng healthcheck của compose
#    nhưng vẫn giữ fallback phòng chắc ăn)
# -------------------------------------------------------
echo "⏳ Waiting for database..."
max_retries=30
count=0
until mysqladmin ping -h"${DB_HOST:-db}" -u"${DB_USERNAME:-root}" -p"${DB_PASSWORD:-password}" --silent 2>/dev/null; do
    count=$((count + 1))
    if [ "$count" -ge "$max_retries" ]; then
        echo "❌ Database not reachable after ${max_retries} retries. Exiting."
        exit 1
    fi
    echo "   Retry ${count}/${max_retries}..."
    sleep 2
done
echo "✅ Database is ready!"

# -------------------------------------------------------
# 3. Run migrations
# -------------------------------------------------------
echo "🔄 Running migrations..."
php artisan migrate --force

# -------------------------------------------------------
# 4. Clear & re-cache config, routes, views
# -------------------------------------------------------
echo "🧹 Optimizing..."
php artisan optimize:clear
php artisan optimize

echo "======================================"
echo "✨  Application is ready!"
echo "======================================"

# Chạy lệnh chính (php-fpm)
exec "$@"
