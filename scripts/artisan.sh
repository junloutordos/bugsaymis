#!/bin/bash
# artisan.sh — safe wrapper for running artisan commands in the container
# Automatically fixes file ownership after every run so www-data keeps access.
# Usage: bash scripts/artisan.sh migrate --force
#        bash scripts/artisan.sh db:seed --class=SomeSeeder --force

docker exec bugsaymis_app php artisan "$@"
docker exec -u root bugsaymis_app chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
