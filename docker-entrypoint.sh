#!/bin/sh
set -e

echo "🚀 Starting Laravel application entrypoint..."

# Wait for database to be ready (with retry logic)
echo "⏳ Waiting for database connection..."
max_attempts=30
attempt=0

while [ $attempt -lt $max_attempts ]; do
  if php artisan migrate:status >/dev/null 2>&1; then
    echo "✅ Database connection established"
    break
  fi
  attempt=$((attempt + 1))
  echo "   Attempt $attempt/$max_attempts - database not ready, waiting 2 seconds..."
  sleep 2
done

if [ $attempt -eq $max_attempts ]; then
  echo "⚠️  Warning: Could not connect to database after $max_attempts attempts"
  echo "   Continuing anyway - migrations will be retried on next startup..."
fi

# Run migrations
echo "📦 Running database migrations..."
php artisan migrate --force || {
  echo "⚠️  Migration failed, but continuing startup..."
}

# Clear and cache config
echo "⚡ Optimizing Laravel..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Start supervisor (which manages nginx, php-fpm, and queue worker)
echo "🎯 Starting supervisor (nginx + php-fpm + queue worker)..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

