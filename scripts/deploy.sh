#!/bin/bash
# deploy.sh — run this after every git pull / merge on production
# Usage: bash scripts/deploy.sh

set -e
cd "$(dirname "$0")/.."

echo "[deploy] Installing production dependencies (with faker)..."
docker run --rm --network host \
  -v "$(pwd):/app" \
  composer:2 composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs

echo "[deploy] Running database migrations..."
docker exec bugsaymis_app php artisan migrate --force

echo "[deploy] Fixing storage permissions (must run after every artisan call)..."
docker exec -u root bugsaymis_app chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
docker exec -u root bugsaymis_app chmod -R 775 /var/www/storage /var/www/bootstrap/cache

echo "[deploy] Rebuilding frontend assets..."
npm run build

echo "[deploy] Regenerating Ziggy routes..."
docker exec bugsaymis_app php artisan ziggy:generate

echo "[deploy] Clearing and rebuilding caches..."
docker exec bugsaymis_app php artisan optimize:clear
docker exec bugsaymis_app php artisan optimize

echo "[deploy] Restarting queue workers..."
docker exec bugsaymis_app php artisan queue:restart

echo "[deploy] Fixing permissions again after cache rebuild..."
docker exec -u root bugsaymis_app chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

echo "[deploy] Done. Site is up."
