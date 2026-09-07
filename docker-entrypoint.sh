#!/bin/bash
set -ex

echo "Starting container setup..."
php artisan config:clear || true

echo "Optimizing Laravel..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Creating storage link..."
php artisan storage:link || true

echo "Setup complete! Starting Apache..."
exec "$@"