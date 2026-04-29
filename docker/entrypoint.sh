#!/bin/bash
set -e

echo "======================================"
echo "🚀  English Class — Starting up..."
echo "======================================"

# -------------------------------------------------------
# 1. Tạo đầy đủ thư mục storage (volume rỗng lần đầu)
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
# 2. Chờ MySQL sẵn sàng
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
# 4. Tạo storage symlink (public/storage -> storage/app/public)
#    PHẢI chạy TRƯỚC khi copy sang public_shared!
# -------------------------------------------------------
if [ ! -L /app/public/storage ]; then
    echo "🔗 Creating storage symlink..."
    php artisan storage:link || true
fi

# -------------------------------------------------------
# 5. Copy toàn bộ public/ (kể cả symlink vừa tạo) sang
#    public_shared/ để Nginx đọc qua named volume
# -------------------------------------------------------
echo "📂 Syncing public files for Nginx..."
mkdir -p /app/public_shared
cp -a /app/public/. /app/public_shared/

# -------------------------------------------------------
# 6. Optimize (chạy với www-data để file cache đúng owner)
# -------------------------------------------------------
echo "🧹 Optimizing..."
php artisan optimize:clear
php artisan optimize

# Fix quyền sau optimize (cache mới tạo bởi root → đổi về www-data)
chown -R www-data:www-data /app/bootstrap/cache 2>/dev/null || true

echo "======================================"
echo "✨  Application is ready!"
echo "======================================"

exec "$@"
