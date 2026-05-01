#!/bin/bash
set -e

echo "======================================"
echo "🚀  English Class — Starting up..."
echo "======================================"

# -------------------------------------------------------
# 1. Check for dependencies
# -------------------------------------------------------
if [ ! -f "vendor/autoload.php" ]; then
    echo "❌ vendor/autoload.php not found!"
    echo "   Ensure you have run 'composer install' or that the volume mount is correct."
    # We don't exit here because maybe the container is intended to be used for installing dependencies
    # but most artisan commands will fail.
fi

# -------------------------------------------------------
# 2. Wait for MySQL if DB_HOST is set
# -------------------------------------------------------
if [ -n "$DB_HOST" ]; then
    echo "⏳ Waiting for database ($DB_HOST)..."
    max_retries=30
    count=0
    # Try to connect to DB port
    until (echo > /dev/tcp/$DB_HOST/${DB_PORT:-3306}) >/dev/null 2>&1; do
        count=$((count + 1))
        if [ "$count" -ge "$max_retries" ]; then
            echo "❌ Database ($DB_HOST) not reachable after ${max_retries} retries."
            # We don't exit yet, artisan might give a better error message
            break
        fi
        echo "   Retry ${count}/${max_retries}..."
        sleep 2
    done
    if [ "$count" -lt "$max_retries" ]; then
        echo "✅ Database is reachable!"
        # Small sleep to ensure MySQL is fully initialized
        sleep 2
    fi
fi

# -------------------------------------------------------
# 3. Laravel Optimization
# -------------------------------------------------------
echo "🧹 Cleaning and caching configuration..."
# We use || true because failing to clear cache shouldn't crash the container
php artisan config:clear || echo "⚠️  Warning: config:clear failed"
php artisan route:clear || echo "⚠️  Warning: route:clear failed"
php artisan view:clear || echo "⚠️  Warning: view:clear failed"
php artisan event:clear || echo "⚠️  Warning: event:clear failed"

# In production, we usually want to cache
if [ "$APP_ENV" = "production" ]; then
    echo "📦 Caching for production..."
    php artisan config:cache || echo "⚠️  Warning: config:cache failed"
    php artisan route:cache || echo "⚠️  Warning: route:cache failed"
    php artisan view:cache || echo "⚠️  Warning: view:cache failed"
fi

# -------------------------------------------------------
# 4. Run migrations if requested
# -------------------------------------------------------
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "🔄 Running migrations..."
    php artisan migrate --force || echo "❌ Migration failed!"
fi

# -------------------------------------------------------
# 5. Set permissions
# -------------------------------------------------------
echo "🔐 Setting permissions..."
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

echo "======================================"
echo "✨  Application is ready!"
echo "======================================"

exec "$@"
