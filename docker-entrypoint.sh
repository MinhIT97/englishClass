#!/bin/bash
set -e

# -------------------------------------------------------
# 1. Đảm bảo các thư mục tồn tại
# -------------------------------------------------------
mkdir -p storage/app/public storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache
# Không dùng chown -R ở đây để tránh chậm startup, Dockerfile đã chown sẵn rồi.

# -------------------------------------------------------
# 2. Chỉ chạy các tác vụ nặng cho container App
# -------------------------------------------------------
if [ "$CONTAINER_ROLE" = "app" ]; then
    echo "🚀 Starting App Role..."

    # Kiểm tra nhanh Database
    if [ -n "$DB_HOST" ]; then
        echo "⏳ Checking database connection ($DB_HOST)..."
        timeout 5s bash -c "until (echo > /dev/tcp/$DB_HOST/${DB_PORT:-3306}) >/dev/null 2>&1; do sleep 1; done" || echo "⚠️ DB not ready, skipping wait..."
    fi

    # Chỉ chạy migration nếu được yêu cầu
    if [ "$RUN_MIGRATIONS" = "true" ]; then
        echo "🔄 Running migrations..."
        php artisan migrate --force --no-interaction || echo "❌ Migration failed!"
    fi

    # Đảm bảo quyền ghi cho storage và cache (chỉ quét các folder cần thiết để nhanh hơn)
    echo "Setting permissions..."
    for dir in storage storage/framework storage/framework/sessions storage/framework/views storage/framework/cache storage/logs bootstrap/cache; do
        mkdir -p /app/$dir
        chown www-data:www-data /app/$dir
        chmod 775 /app/$dir
    done

    # Link storage nếu chưa có
    if [ ! -L public/storage ]; then
        php artisan storage:link || true
    fi

    # Sync Assets ra shared volume cho Nginx
    if [ -d "/app/public_shared" ]; then
        echo "📂 Syncing assets to Nginx volume..."
        find /app/public_shared -mindepth 1 -maxdepth 1 -exec rm -rf {} +
        cp -a /app/public/. /app/public_shared/
    fi
    
    echo "✨ App is ready!"
fi

# Thực thi lệnh chính
exec "$@"
