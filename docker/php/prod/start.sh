#!/bin/bash
set -e

# Copy .env.docker if .env doesn't exist
if [ ! -f .env ]; then
    cp .env.docker .env
fi

# Generate app key if not set
if ! grep -q "APP_KEY=" .env || [ "$(grep 'APP_KEY=' .env | cut -d= -f2)" = "" ]; then
    php artisan key:generate --force
fi

# Install PHP dependencies if vendor doesn't exist
if [ ! -d vendor ]; then
    composer install --no-interaction --prefer-dist
fi

# Install Node dependencies if node_modules doesn't exist
if [ ! -d node_modules ]; then
    npm install
fi

# Ensure storage and bootstrap/cache directories exist with proper permissions
mkdir -p storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache \
    storage/framework/data \
    storage/logs \
    bootstrap/cache

# Remove old log files that may have wrong ownership, then create fresh ones
rm -f storage/logs/laravel.log

chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Run migrations
php artisan migrate --force --graceful 2>/dev/null || true

# Create storage link
php artisan storage:link 2>/dev/null || true

# Start Vite HMR in background for hot-reload (only in dev)
npm run build &

exec php-fpm