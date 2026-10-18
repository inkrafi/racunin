#!/bin/sh
set -e

echo "🚀 Starting Racunin container ($*)..."

if [ -z "$APP_KEY" ]; then
    echo "❌ APP_KEY is not set. Generate one with: php artisan key:generate --show"
    exit 1
fi

# Only one container (the web app) should migrate; workers/scheduler share this image
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "📦 Running database migrations..."
    php artisan migrate --force
fi

# Warm up production caches (config is read from the container environment)
echo "⚡ Caching configuration, routes, views & events..."
php artisan optimize

# Ensure storage permissions (volume may be freshly mounted)
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

echo "✅ Ready."

exec "$@"
