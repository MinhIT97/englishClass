#!/bin/bash
set -e

# -------------------------------------------------------
# 1. Đảm bảo các thư mục tồn tại (Rất nhanh)
# -------------------------------------------------------
mkdir -p storage/app/public storage/framework/{cache/data,sessions,views} storage/logs

# -------------------------------------------------------
# 2. Chỉ chạy các tác vụ nặng cho container App
# -------------------------------------------------------
if [ "$CONTAINER_ROLE" = "app" ]; then
    # Kiểm tra nhanh Database (không đợi quá lâu)
    if [ -n "$DB_HOST" ]; then
        timeout 5s bash -c "until (echo > /dev/tcp/$DB_HOST/${DB_PORT:-3306}) >/dev/null 2>&1; do sleep 1; done" || echo "⚠️ DB not ready, skipping wait..."
    fi

    # Chỉ chạy migration nếu được yêu cầu
    if [ "$RUN_MIGRATIONS" = "true" ]; then
        php artisan migrate --force --no-interaction || echo "❌ Migration failed!"
    fi

    # Link storage nếu chưa có
    if [ ! -L public/storage ]; then
        php artisan storage:link || true
    fi

    # Sync Assets (Chỉ copy nếu có thay đổi và không chown lại toàn bộ)
    if [ -d "/app/public_shared" ]; then
        cp -ru /app/public/. /app/public_shared/
    fi
fi

# Thực thi lệnh chính (PHP-FPM hoặc Queue)
exec "$@"
