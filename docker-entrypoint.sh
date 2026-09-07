#!/bin/bash
set -ex

echo "Starting container setup..."
php artisan config:clear || true

echo "Optimizing Laravel..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Running migrations..."
php artisan migrate --force

echo "Creating storage link..."
php artisan storage:link

echo "Setup complete! Starting Apache..."
exec "$@"
