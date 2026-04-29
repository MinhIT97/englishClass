#!/bash
set -e

echo "🚀 Starting English Class Laravel Application..."

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

# Clear cache
echo "🧹 Clearing cache..."
php artisan cache:clear
php artisan config:clear

echo "✨ Application is ready!"

# Start PHP-FPM
exec php-fpm
