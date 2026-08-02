#!/bin/bash
set -e

HOST_UID="${HOST_UID:-1000}"
HOST_GID="${HOST_GID:-1000}"

# Align image user `laravel` with the host bind-mount owner so writes
# (npm build, composer) are owned by the same uid outside the container.
if id laravel >/dev/null 2>&1; then
    current_uid="$(id -u laravel)"
    current_gid="$(id -g laravel)"
    if [ "$current_uid" != "$HOST_UID" ]; then
        usermod -u "$HOST_UID" laravel
    fi
    if [ "$current_gid" != "$HOST_GID" ]; then
        groupmod -g "$HOST_GID" laravel || true
        usermod -g "$HOST_GID" laravel || true
    fi
fi

run_as_host() {
    runuser -u laravel -- "$@"
}

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
    run_as_host composer install --no-interaction --prefer-dist
fi

# Install Node dependencies if node_modules doesn't exist
if [ ! -d node_modules ]; then
    run_as_host npm install
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

# Frontend production build as host user (writable public/build on the host)
mkdir -p public/build
chown -R "${HOST_UID}:${HOST_GID}" public/build
run_as_host npm run build

exec php-fpm
