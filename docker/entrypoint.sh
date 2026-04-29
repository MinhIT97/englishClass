#!/bin/bash
set -e

echo "======================================"
echo "🚀  English Class — Starting up..."
echo "======================================"

# -------------------------------------------------------
# 1. Tạo đầy đủ thư mục storage mà Laravel cần
#    (quan trọng vì storage được mount từ named volume rỗng)
# -------------------------------------------------------
echo "📁 Creating storage directories..."
mkdir -p \
    /app/storage/app/public \
    /app/storage/framework/cache/data \
    /app/storage/framework/sessions \
    /app/storage/framework/views \
    /app/storage/logs
chown -R www-data:www-data /app/storage
chmod -R 775 /app/storage

# -------------------------------------------------------
# 2. Copy public/ sang public_shared/ cho Nginx đọc static files
# -------------------------------------------------------
echo "📂 Syncing public files for Nginx..."
mkdir -p /app/public_shared
cp -a /app/public/. /app/public_shared/

# -------------------------------------------------------
# 3. Chờ MySQL sẵn sàng (có healthcheck rồi nhưng giữ fallback)
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
# 4. Run migrations
# -------------------------------------------------------
echo "🔄 Running migrations..."
php artisan migrate --force

# -------------------------------------------------------
# 5. Tạo lại symlink storage
# -------------------------------------------------------
if [ ! -L /app/public/storage ]; then
    echo "🔗 Creating storage symlink..."
    php artisan storage:link || true
fi

# -------------------------------------------------------
# 6. Clear & optimize
# -------------------------------------------------------
echo "🧹 Optimizing..."
php artisan optimize:clear
php artisan optimize

echo "======================================"
echo "✨  Application is ready!"
echo "======================================"

exec "$@"
