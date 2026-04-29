#!/bin/bash
set -e

echo "🚀 Starting English Class Laravel Application..."

# Copy public files to shared volume for Nginx IMMEDIATELY
if [ -d "/app/public_shared" ]; then
    echo "📂 Syncing public files for Nginx..."
    cp -a /app/public/. /app/public_shared/
fi

# Wait for database to be ready
echo "⏳ Waiting for database to be ready..."
until mysqladmin ping -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" --silent; do
  echo '.'
  sleep 1
done
echo "✅ Database is ready!"

# Run migrations
echo "🔄 Running database migrations..."
php artisan migrate --force

# Optimize
echo "🧹 Optimizing..."
php artisan optimize:clear
php artisan optimize

echo "✨ Application is ready!"

# Execute main command
exec "$@"
