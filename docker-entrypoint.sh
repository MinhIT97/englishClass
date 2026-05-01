#!/bin/bash
set -e

echo "======================================"
echo "🚀  English Class — Starting up as $CONTAINER_ROLE..."
echo "======================================"

# -------------------------------------------------------
# 1. Các bước cơ bản (Cả App và Queue đều cần)
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
# 2. Các bước chỉ dành cho container chính (App)
# -------------------------------------------------------
if [ "$CONTAINER_ROLE" = "app" ]; then
    # Đợi Database
    if [ -n "$DB_HOST" ]; then
        echo "⏳ Waiting for database ($DB_HOST)..."
        max_retries=30
        count=0
        until (echo > /dev/tcp/$DB_HOST/${DB_PORT:-3306}) >/dev/null 2>&1; do
            count=$((count + 1))
            if [ "$count" -ge "$max_retries" ]; then break; fi
            sleep 2
        done
        echo "✅ Database is ready!"
    fi

    echo "🧹 Optimizing Laravel..."
    php artisan config:clear || true
    php artisan route:clear || true
    php artisan view:clear || true

    if [ "$APP_ENV" = "production" ]; then
        php artisan config:cache || true
        php artisan route:cache || true
        php artisan view:cache || true
    fi

    if [ "$RUN_MIGRATIONS" = "true" ]; then
        echo "🔄 Running migrations..."
        php artisan migrate --force || echo "❌ Migration failed!"
    fi

    if [ ! -L public/storage ]; then
        php artisan storage:link || true
    fi

    if [ -d "/app/public_shared" ]; then
        echo "📂 Syncing public files to shared volume..."
        cp -ru /app/public/. /app/public_shared/
        chown -R www-data:www-data /app/public_shared
        chmod -R 755 /app/public_shared
    fi
fi

echo "======================================"
echo "✨  $CONTAINER_ROLE is ready!"
echo "======================================"

exec "$@"
