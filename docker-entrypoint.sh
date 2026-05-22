#!/bin/bash
set -e

# ── Secrets Manager: fetch Google Drive credentials ───────────────────────────
# Pull credentials JSON from Secrets Manager and write to disk.
# The env var GOOGLE_DRIVE_CREDENTIALS_JSON is no longer used — secret lives only in SM.
GOOGLE_JSON=$(aws secretsmanager get-secret-value \
  --secret-id crcmis/google-drive-credentials \
  --region ap-southeast-1 \
  --query SecretString \
  --output text 2>/dev/null || echo "")

if [ -n "$GOOGLE_JSON" ]; then
    echo "$GOOGLE_JSON" > /var/www/google-credentials.json
    chmod 600 /var/www/google-credentials.json
    chown www-data:www-data /var/www/google-credentials.json
fi

# ── Export env vars for cron (restricted to non-secret vars) ──────────────────
# Exclude secrets — DB_PASSWORD, APP_KEY, SOKETI secret are injected by ECS
# at runtime and available to processes; cron reads /etc/environment on start.
printenv | grep -vE "^(no_proxy|GOOGLE_DRIVE_CREDENTIALS_JSON|LS_COLORS|GPG_KEYS|PHP_(ASC|SHA|CFLAGS|CPPFLAGS|LDFLAGS|URL|INI|VERSION)|PHPIZE_DEPS)" \
  > /etc/environment
chmod 600 /etc/environment

# ── Ensure writable directories are owned by www-data ─────────────────────────
chown -R www-data:www-data \
    /var/www/storage \
    /var/www/bootstrap/cache

# ── Laravel bootstrap ─────────────────────────────────────────────────────────
php /var/www/artisan cache:clear
php /var/www/artisan migrate --force
php /var/www/artisan app:version-sync
php /var/www/artisan config:cache
php /var/www/artisan route:cache
php /var/www/artisan view:cache

# Signal any queue worker from a previous (still draining) ECS task to exit
# gracefully so it stops processing jobs with the OLD codebase. The fresh
# worker started below by supervisord will pick up the new code.
# Prevents "__PHP_Incomplete_Class" errors on jobs dispatched right after a deploy.
php /var/www/artisan queue:restart || true

# ── Start services via supervisord ─────────────────────────────────────────────
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/bugsaymis.conf
