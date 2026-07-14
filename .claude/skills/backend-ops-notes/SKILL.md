---
name: backend-ops-notes
description: Mail (Gmail SMTP) config and DB backup cron gotchas. Use when working on mail sending, the BackupDatabase command, or BackupVerify.
---

## Mail
- Provider: Gmail SMTP (`smtp.gmail.com:587`, TLS)
- From: `portal@crc.pshs.edu.ph` / `PSHS-CRC MIS`
- Credentials stored in SSM Parameter Store (`/crcmis/prod/MAIL_*`)
- App password (not Google account password) — 16-character app-specific password

## DB Backup (Cron)
- Runs via cron inside the container (supervisord manages cron service)
- `exec()` calls `mysqldump` then `gzip -f` — do NOT disable `exec` in PHP
- Dumps to `sys_get_temp_dir()` temp file (cleaned in `finally`) — no `storage_path` local copy
- Compressed backup (~3-5MB) uploaded to Google Drive using service account
- Google credentials fetched from Secrets Manager at container startup
- `BackupVerify` command checks Google Drive for a backup within the last 25h (no local files to check)
- Schedule lives in `routes/console.php` (canonical); `app/Console/Kernel.php` must not duplicate entries
- Windows: 06:00 PHT (backup), 06:30 PHT (verify) — added May 2026
