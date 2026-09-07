#!/bin/sh

echo "Starting application startup sequence..."

# Crear carpetas necesarias para Laravel y Nginx
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/storage/app/public \
         /var/www/html/bootstrap/cache \
         /run/nginx \
         /var/log/nginx \
         /var/lib/nginx/tmp/client_body \
         /var/lib/nginx/tmp/proxy \
         /var/lib/nginx/tmp/fastcgi 2>/dev/null || true

# Consolidar imágenes iniciales si public/storage vino como carpeta desde Git
if [ -d /var/www/html/public/storage ] && [ ! -L /var/www/html/public/storage ]; then
    echo "Consolidating initial images into storage/app/public..."
    cp -rn /var/www/html/public/storage/* /var/www/html/storage/app/public/ 2>/dev/null || true
    rm -rf /var/www/html/public/storage
fi

# Recrear el enlace simbólico limpio (public/storage -> storage/app/public)
rm -f /var/www/html/public/storage
ln -sf /var/www/html/storage/app/public /var/www/html/public/storage

# Asignar permisos correctos a Laravel, fotos y Nginx
chown -R nginx:nginx /var/www/html/storage /var/www/html/public /var/www/html/bootstrap/cache /run/nginx /var/log/nginx /var/lib/nginx 2>/dev/null || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# Generar APP_KEY si falta
if [ -z "$APP_KEY" ]; then
    echo "Generating application key..."
    php /var/www/html/artisan key:generate --force 2>/dev/null || true
fi

# Cachés para producción si APP_ENV es production
if [ "$APP_ENV" = "production" ]; then
    echo "Caching configuration, routes and views..."
    php /var/www/html/artisan config:cache 2>/dev/null || true
    php /var/www/html/artisan route:cache 2>/dev/null || true
    php /var/www/html/artisan view:cache 2>/dev/null || true
fi

# Ejecutar migraciones si se solicita
if [ "$AUTORUN_MIGRATIONS" = "true" ]; then
    echo "Running database migrations..."
    php /var/www/html/artisan migrate --force 2>/dev/null || true
fi

echo "Startup complete. Launching Supervisord (Nginx + PHP-FPM)..."

# Ejecutar Supervisord en primer plano
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
