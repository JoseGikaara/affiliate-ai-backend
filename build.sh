#!/bin/bash
# Production build script for Render

set -e

echo "🔨 Building AI Affiliate System Backend..."

# Install dependencies
echo "📦 Installing dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Generate app key if not set
if [ -z "$APP_KEY" ]; then
    echo "🔑 Generating application key..."
    php artisan key:generate --force
fi

# Run migrations
echo "🗄️ Running migrations..."
php artisan migrate --force

# Clear and cache config
echo "⚡ Optimizing for production..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "✅ Build complete!"

