#!/bin/bash
set -e

# ── Pre-deploy migration mode (blue-green) ────────────────────────────────────
# Invoked by the deploy pipeline as a standalone one-off ECS task BEFORE any
# new code serves traffic: `docker-entrypoint.sh migrate`. Runs migrations once
# against the shared RDS and exits with the migrate status. Migrations are NO
# LONGER run on normal container boot — blue and green run side-by-side against
# one schema, so every migration must be backward-compatible (expand/contract).
# See CLAUDE.md → "Migration discipline (blue-green)".
if [ "$1" = "migrate" ]; then
    php /var/www/artisan migrate --force
    exit $?
fi

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
# Values must be shell-quoted: this file is sourced by bash in the cron line,
# and unquoted values with spaces (e.g. MAIL_FROM_NAME="PSHS-CRC MIS") abort
# the source mid-file, dropping every var after the bad line.
printenv | grep -vE "^(no_proxy|GOOGLE_DRIVE_CREDENTIALS_JSON|LS_COLORS|GPG_KEYS|PHP_(ASC|SHA|CFLAGS|CPPFLAGS|LDFLAGS|URL|INI|VERSION)|PHPIZE_DEPS)" \
  | while IFS='=' read -r key value; do
      case "$key" in ''|*[!A-Za-z0-9_]*) continue ;; esac
      printf "%s='%s'\n" "$key" "$(printf '%s' "$value" | sed "s/'/'\\\\''/g")"
    done > /etc/environment
chmod 600 /etc/environment

# ── Ensure writable directories are owned by www-data ─────────────────────────
chown -R www-data:www-data \
    /var/www/storage \
    /var/www/bootstrap/cache

# ── Laravel bootstrap ─────────────────────────────────────────────────────────
# NOTE: `migrate --force` deliberately removed here — migrations now run as a
# dedicated pre-deploy one-off task (see the `migrate` mode at the top of this
# file and the deploy pipeline). Do NOT re-add it: in blue-green it would run on
# every green boot while blue still serves live traffic.
php /var/www/artisan cache:clear
php /var/www/artisan config:cache

# ── Worker mode (cron + queue only) ───────────────────────────────────────────
# Invoked by the dedicated worker service as `docker-entrypoint.sh worker`.
# Runs cron (Laravel scheduler) + the queue worker ONLY — no nginx/php-fpm, and
# crucially this service runs as a single rolling task so scheduled jobs and
# queued work fire exactly once (the web service no longer runs cron/queue, so
# blue+green web tasks can't double-fire them during a blue-green bake).
if [ "$1" = "worker" ]; then
    # Tell any previous (draining) worker to finish its current job and exit so
    # it stops running OLD code. The fresh worker below picks up the new code.
    php /var/www/artisan queue:restart || true
    exec /usr/bin/supervisord -c /etc/supervisor/conf.d/bugsaymis-worker.conf
fi

# ── Web mode (default): nginx + php-fpm ───────────────────────────────────────
php /var/www/artisan app:version-sync
php /var/www/artisan route:cache
php /var/www/artisan view:cache

# Signal the queue worker (now in the separate worker service) to reload code
# after a deploy. The broadcast goes through Redis and reaches the worker task.
php /var/www/artisan queue:restart || true

# ── Start services via supervisord ─────────────────────────────────────────────
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/bugsaymis.conf
