#!/bin/bash
set -e

echo "Optimizando Laravel para producción..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Ejecutando migraciones..."
php artisan migrate --force

echo "Creando storage link..."
php artisan storage:link

exec "$@"
