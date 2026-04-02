#!/usr/bin/env bash
# =============================================================================
# BugsayMIS — Database Restore Runbook
# =============================================================================
# Usage:
#   ./scripts/restore.sh                    # Restore latest local backup
#   ./scripts/restore.sh backup_2026-04-02_08-00-00.sql   # Specific file
#
# Run from the project root on the production server (not inside a container).
# Requires: docker, mysql client, access to the running bugsaymis_db container.
# =============================================================================
set -euo pipefail

BACKUP_DIR="storage/app/db_backups"
DB_CONTAINER="bugsaymis_db"
DB_NAME="bugsaymis"
DB_ROOT_PASS="${DB_BACKUP_PASSWORD:-F9oPkWUZJEf7v1NtbKE0XNrtxF6cTbf}"

# ── Resolve backup file ──────────────────────────────────────────────────────
if [[ $# -ge 1 ]]; then
    BACKUP_FILE="$1"
    if [[ ! "$BACKUP_FILE" = /* ]]; then
        BACKUP_FILE="${BACKUP_DIR}/${BACKUP_FILE}"
    fi
else
    # Pick the most recent backup
    BACKUP_FILE=$(ls -t "${BACKUP_DIR}"/backup_*.sql 2>/dev/null | head -1)
fi

if [[ -z "$BACKUP_FILE" || ! -f "$BACKUP_FILE" ]]; then
    echo "ERROR: No backup file found at '${BACKUP_FILE:-<none>}'"
    echo "Available backups:"
    ls -lh "${BACKUP_DIR}"/backup_*.sql 2>/dev/null || echo "  (none)"
    exit 1
fi

BASENAME=$(basename "$BACKUP_FILE")
SIZE=$(du -sh "$BACKUP_FILE" | cut -f1)

echo "============================================================"
echo "  BugsayMIS Database Restore"
echo "============================================================"
echo "  Backup file : ${BACKUP_FILE}"
echo "  File size   : ${SIZE}"
echo "  Target DB   : ${DB_NAME} (container: ${DB_CONTAINER})"
echo "============================================================"
echo ""
echo "WARNING: This will OVERWRITE the entire '${DB_NAME}' database."
echo "All current data will be replaced with the backup contents."
echo ""
read -r -p "Type 'yes' to proceed: " CONFIRM

if [[ "$CONFIRM" != "yes" ]]; then
    echo "Restore cancelled."
    exit 0
fi

# ── Step 1: Put Laravel in maintenance mode ──────────────────────────────────
echo ""
echo "[1/5] Enabling maintenance mode..."
docker exec bugsaymis_app php artisan down --secret="restore-in-progress" || true

# ── Step 2: Verify the backup file before restoring ──────────────────────────
echo "[2/5] Verifying backup integrity..."
docker exec bugsaymis_app php artisan backup:verify --file="${BASENAME}" || {
    echo "ERROR: Backup verification failed. Aborting restore."
    docker exec bugsaymis_app php artisan up || true
    exit 1
}

# ── Step 3: Drop and recreate the database ───────────────────────────────────
echo "[3/5] Dropping and recreating database '${DB_NAME}'..."
docker exec -i "${DB_CONTAINER}" mysql \
    -uroot -p"${DB_ROOT_PASS}" \
    -e "DROP DATABASE IF EXISTS \`${DB_NAME}\`; CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# ── Step 4: Restore from backup ──────────────────────────────────────────────
echo "[4/5] Restoring from ${BASENAME}..."
docker exec -i "${DB_CONTAINER}" mysql \
    -uroot -p"${DB_ROOT_PASS}" \
    --skip-ssl \
    "${DB_NAME}" < "${BACKUP_FILE}"

echo "      Restore complete."

# ── Step 5: Run migrations and clear caches ───────────────────────────────────
echo "[5/5] Running post-restore steps..."
docker exec bugsaymis_app php artisan migrate --force --no-interaction
docker exec bugsaymis_app php artisan optimize:clear

# ── Bring Laravel back up ────────────────────────────────────────────────────
docker exec bugsaymis_app php artisan up

echo ""
echo "============================================================"
echo "  Restore COMPLETE"
echo "  Source: ${BASENAME}"
echo "  Restored at: $(date '+%Y-%m-%d %H:%M:%S')"
echo "============================================================"
echo ""
echo "Post-restore checklist:"
echo "  [ ] Verify login works at https://bugsaymis.online"
echo "  [ ] Verify /_status returns HTTP 200"
echo "  [ ] Check storage/logs/laravel-$(date +%Y-%m-%d).log for errors"
echo "  [ ] Notify team that system is restored"
