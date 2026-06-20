#!/bin/bash
########################################################
# Aegis Filter – Docker Entrypoint
# Ejecuta preparación de Laravel antes de iniciar Apache
########################################################

set -e

echo "==================================================="
echo "  Aegis Filter – Iniciando contenedor..."
echo "==================================================="

# Laravel requires these writable directories before clearing cached views.
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Esperar que MySQL esté disponible
echo ">> Esperando conexión a base de datos..."
until php artisan db:show &>/dev/null; do
  echo "   Base de datos no disponible, reintentando en 3s..."
  sleep 3
done

echo ">> Ejecutando migraciones..."
php artisan migrate --force

echo ">> Limpiando cache de configuración..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

echo ">> Generando enlace simbólico de storage..."
php artisan storage:link || true

echo ">> Permisos de storage..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

echo "==================================================="
echo "  Aegis Filter – Listo. Iniciando Apache..."
echo "==================================================="

exec "$@"
