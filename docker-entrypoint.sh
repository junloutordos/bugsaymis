#!/bin/bash

# Export container environment variables so cron and supervisor can use them
printenv | grep -v "no_proxy" > /etc/environment

# Write Google service-account credentials to disk if injected via SSM
if [ -n "$GOOGLE_DRIVE_CREDENTIALS_JSON" ]; then
    echo "$GOOGLE_DRIVE_CREDENTIALS_JSON" > /var/www/google-credentials.json
    chmod 600 /var/www/google-credentials.json
fi

# Clear application cache so stale values (e.g. app version) don't persist across deploys
php /var/www/artisan cache:clear

# Run pending migrations (safe — skips already-run migrations)
php /var/www/artisan migrate --force

# Cache config/routes/views for production performance
php /var/www/artisan config:cache
php /var/www/artisan route:cache
php /var/www/artisan view:cache

# Start all services via supervisord (nginx + php-fpm + cron + queue worker)
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/bugsaymis.conf
