#!/bin/bash
set -e

echo "======================================"
echo "🚀  English Class — Starting up..."
echo "======================================"

# -------------------------------------------------------
# 1. Khởi tạo thư mục storage nếu mount volume rỗng
# -------------------------------------------------------
echo "📁 Checking storage directories..."
mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# -------------------------------------------------------
# 2. Đợi Database sẵn sàng
# -------------------------------------------------------
if [ -n "$DB_HOST" ]; then
    echo "⏳ Waiting for database ($DB_HOST)..."
    max_retries=30
    count=0
    until (echo > /dev/tcp/$DB_HOST/${DB_PORT:-3306}) >/dev/null 2>&1; do
        count=$((count + 1))
        if [ "$count" -ge "$max_retries" ]; then
            echo "❌ Database not reachable after ${max_retries} retries."
            break
        fi
        echo "   Retry ${count}/${max_retries}..."
        sleep 2
    done
    if [ "$count" -lt "$max_retries" ]; then
        echo "✅ Database is ready!"
        sleep 1
    fi
fi

# -------------------------------------------------------
# 3. Laravel Optimization
# -------------------------------------------------------
echo "🧹 Optimizing Laravel..."
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

if [ "$APP_ENV" = "production" ]; then
    echo "📦 Caching for production..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

# -------------------------------------------------------
# 4. Run migrations if requested
# -------------------------------------------------------
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "🔄 Running migrations..."
    php artisan migrate --force || echo "❌ Migration failed!"
fi

# -------------------------------------------------------
# 5. Storage Link
# -------------------------------------------------------
if [ ! -L public/storage ]; then
    echo "🔗 Creating storage symlink..."
    php artisan storage:link || true
fi

# -------------------------------------------------------
# 6. Sync Public Assets for Nginx
#    (Vì Nginx dùng volume riêng 'app_public' để đọc static files)
# -------------------------------------------------------
if [ -d "/app/public_shared" ]; then
    echo "📂 Syncing public files to shared volume..."
    cp -ru /app/public/. /app/public_shared/
    chown -R www-data:www-data /app/public_shared
    chmod -R 755 /app/public_shared
fi

echo "======================================"
echo "✨  Application is ready!"
echo "======================================"

exec "$@"
