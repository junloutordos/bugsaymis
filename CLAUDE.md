# BugSayMis — PSHS-CRC Campus Management Information System

## Project Overview
BugSayMis (also referred to as CRCMIS) is a comprehensive MIS for the Philippine Science High School – Caraga Region Campus (PSHS-CRC). It covers HR, payroll, faculty loading, performance management (IPCR/PMS), recruitment, library, service requests, SALN, document tracking, class records, WFH attendance, and more.

## Tech Stack
| Layer | Technology |
|---|---|
| Backend | Laravel 12, PHP 8.4 (container) |
| Frontend | Vue 3 (`<script setup>`), Inertia.js 2, Tailwind CSS 3 |
| Build | Vite 7 |
| Database | MySQL 8.0 (AWS RDS — encrypted at rest) |
| Real-time | Laravel Echo + Pusher + Soketi |
| PDF | mPDF 8 — always use `sys_get_temp_dir()` for tempDir, never `storage_path()` |
| Excel | PhpSpreadsheet + Maatwebsite Excel |
| Icons | Heroicons 2 (`@heroicons/vue/24/outline`) |
| File Storage | AWS S3 (`Storage::disk('s3')`) — **never** `disk('public')` |
| Container | Docker (dev service name = `php`, NOT `app`) |

---

## Infrastructure

### Development
```
/Users/junlou/bugsaymis-docker/          # Docker Compose root
  src/bugsaymis/                         # Laravel app (this repo)
  nginx/default.conf                     # Dev nginx config
Services: php (PHP-FPM), mysql, nginx, soketi, phpmyadmin
Dev URL: http://localhost:8080
PhpMyAdmin: http://localhost:8081
```

### Production (AWS ECS Fargate)
```
Cluster:       crcmis-prod
Service:       crcmis-prod-service
Container:     nginx (single container — nginx + PHP-FPM + cron + queue worker)
ECR repo:      971422671747.dkr.ecr.ap-southeast-1.amazonaws.com/crcmis/app
App URL:       https://mis.crc.pshs.edu.ph
Cloudflare:    Proxied (orange-cloud) — WAF active
ALB:           crcmis-alb (HTTPS only, TLS 1.3)
RDS:           crcmis-db-encrypted.c5i2kaqa8hyl.ap-southeast-1.rds.amazonaws.com (encrypted, single-AZ)
Redis:         crcmis-redis-rg.d8qigv.ng.0001.apse1.cache.amazonaws.com:6379 (replication group primary; SSM /crcmis/prod/REDIS_HOST)
S3 bucket:     crcmis-mis-storage (ap-southeast-1, Block Public Access ON)
```

### Secrets — AWS Secrets Manager & SSM
Sensitive config is in **SSM Parameter Store** (`/crcmis/prod/*`) injected as env vars by ECS at startup.
Google Drive credentials are in **AWS Secrets Manager** (`crcmis/google-drive-credentials`) and fetched by `docker-entrypoint.sh` at runtime.

**Never put secrets in code or plaintext env vars.**

### Deployment
Push to `main` → GitHub Actions builds Docker image → pushes to ECR → ECS rolls out new tasks automatically. Migration runs automatically on container start via `docker-entrypoint.sh`.

```bash
# Typical deploy flow
git add <files>
git commit -m "feat/fix: description"
git checkout main && git merge junlou && git push origin main && git checkout junlou
```

### Running Artisan in Dev
```bash
cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan COMMAND"
```

### Running Artisan in Production (ECS exec)
```bash
TASK=$(aws ecs list-tasks --cluster crcmis-prod --query 'taskArns[0]' --output text)
aws ecs execute-command --cluster crcmis-prod --task $TASK --container nginx --interactive --command "php /var/www/artisan COMMAND"
```
**Note:** `artisan tinker` in production is blocked by `open_basedir` for psysh history. Workaround: `env HOME=/tmp php /var/www/artisan tinker --execute='...'`

---

## Critical Rules

### File Uploads — Cloudflare WAF
**Cloudflare blocks `multipart/form-data` file uploads on this app (returns 403 before reaching the server).**
- **Always send files as base64 data URI in a JSON body** — not as `FormData` with `Content-Type: multipart/form-data`
- Pattern: frontend reads file with `FileReader.readAsDataURL()` → sends `{ photo_base64: "data:image/jpeg;base64,..." }` as JSON
- Backend: use the existing `uploadBase64Photo()` helper in `WFHService` or decode manually and `Storage::disk('s3')->put()`
- This applies to ALL file uploads across all modules

### S3 Storage
- Always use `Storage::disk('s3')` — never `disk('public')` (sets ACL=public-read → blocked by S3 Block Public Access → silent failure)
- S3 bucket is **private** — serve files through a proxy route, never via direct S3 URL
- WFH photos use the proxy: `/hr/wfh/photo/{fileId}` where `fileId = 's3.' + base64url(s3Key)`
- S3 key encoding: `'s3.' . rtrim(strtr(base64_encode($s3Key), '+/', '-_'), '=')`

### PHP Security Config (production)
- `open_basedir = /var/www:/tmp:/usr/local/etc/php` — PHP cannot read outside these paths
- `max_execution_time = 120` — scripts time out after 2 minutes
- `allow_url_fopen = Off` — no remote URL fetching via file functions
- `disable_functions` includes: system, shell_exec, passthru, proc_open, popen, pcntl_exec
- `exec()` is **allowed** (needed for DB backup cron via mysqldump)

---

## Directory Structure
```
app/
  Http/Controllers/         # Root + namespaced: HR/, Payroll/, Recruitment/, FacultyLoading/, SALN/, ClassRecord/
  Models/                   # Eloquent models + namespaced: HR/, Payroll/, FacultyLoading/, SALN/, ClassRecord/
  Services/                 # Business logic: WFHService, ClassRecord/GradeComputationService, etc.
  Http/Requests/            # Form Request classes
  Console/Commands/         # BackupDatabase (uses exec + gzip → uploads to Google Drive)
resources/js/
  Pages/                    # Inertia Vue pages
  Components/               # Reusable components (AppTable, AppModal, AppCard, etc.)
  Composables/              # useUsers.js, useIPCR.js, useSubmit.js, etc.
  Layouts/                  # AdminLayout.vue (primary)
  Utils/ClassRecord/        # gradeUtils.js — JS mirror of GradeComputationService
database/migrations/        # 300+ migrations — format: YYYY_MM_DD_HHMMSS_description.php
docker/
  nginx-app.conf            # Production nginx (security headers, rate limiting)
  supervisord.conf          # nginx + php-fpm + cron + queue-worker
docker-entrypoint.sh        # Fetches Google creds from Secrets Manager, runs migrations
Dockerfile                  # Production image (php:8.4-fpm + nginx + awscli)
routes/
  web.php                   # Main routes (~950+ lines)
  auth.php, chat.php, faculty-loading.php, saln.php
```

---

## Backend Conventions

### Controllers
- Always return `Inertia::render('Path/To/Page', [...props])` — never blade views
- Thin controllers; heavy logic goes in Service classes under `app/Services/`
- Authorization: `$this->authorize('permission.string')` or middleware on route

### Permissions & Roles
```php
// Middleware — pipe = ANY, comma = ALL
->middleware('permission:hr.leave.view|hr.leave.approve')  // ANY
->middleware('permission:hr.leave.view,hr.employee.manage') // ALL

// In code
$user->hasPermission('hr.leave.view')
$user->hasAnyPermission(['hr.leave.view', 'hr.leave.approve'])
$user->hasAnyRole(['Administrator', 'AUH', 'CID Chief'])
$user->isSuperAdmin()   // Administrator role bypasses all permission checks
```
Permission string pattern: `module.submodule.action`

### Eloquent Patterns
```php
// Always eager load to avoid N+1
User::with(['role', 'division.divisionchief', 'office'])->get()

// Soft-delete = status column, no Laravel SoftDeletes trait
->where('status', '<>', 'inactive')

// Redirect after mutation
return back()->with('success', 'Record updated.');
return redirect()->route('resource.index')->with('success', 'Created.');
```

### Migrations
- Format: `YYYY_MM_DD_HHMMSS_description.php`
- Add columns with `->after('existing_column')`
- Always write a `down()` method
- Run in dev: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/<file>"`

### Migration discipline (blue-green) ⚠️
Migrations run as a **pre-deploy one-off task** (deploy.yml), **not** on container boot, and during a deploy the **old (blue) and new (green) code run side-by-side against the same RDS schema**. Therefore every migration must be **backward-compatible with the currently-deployed code**:
- **Additive changes are safe** — new nullable columns (`->after()`), new tables, new indexes. This covers ~95% of migrations; nothing changes for these.
- **Destructive changes (drop/rename column, change type, NOT NULL on existing) must be split across TWO deploys** — **expand/contract**:
  1. **Expand:** add the new shape (nullable). Ship code that writes both old + new, reads new-or-old. Old code keeps working.
  2. **Contract (a later deploy, after all code uses the new shape):** drop the old column.
- **Never** drop/rename a column in the same deploy as the code that stops using it — it breaks live blue traffic before the flip.
- `migrate --force` was removed from `docker-entrypoint.sh` on purpose — do not re-add it.

### Key Models
| Model | Key Fields / Notes |
|---|---|
| `User` | `role_id` (legacy), many-to-many `roles`, `division_id`, `office_id`, `salary_grade`, `salary_step`, `status`, `emp_category` |
| `Division` | `division_chief_id`, `status` (active/inactive) |
| `Office` | belongs to `Division` |
| `Role`, `Permission` | RBAC — pivot tables `role_user`, `permission_role` |
| `FacultyLoading\SchoolYear` | `name` (e.g. "2025-2026"), `is_current` boolean — used by Class Record locking |
| `HR\LeaveApplication` | `status`: pending / forwarded / approved / rejected |
| `ClassRecord\ClassRecord` | `school_year_id` FK → `school_years.id`; `isCurrentSchoolYear()` helper |

---

## Frontend Conventions

### Vue Component Pattern
```vue
<script setup>
import { ref, computed, watch } from 'vue'
import { Head, usePage, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { SomeIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ items: Array })
</script>

<template>
  <Head title="Page Title" />
  <AdminLayout title="Page Title">
    <!-- content -->
  </AdminLayout>
</template>
```

### Forms
```js
// Inertia useForm (for standard Inertia form submissions)
const form = useForm({ name: '', status: 'active' })
form.post(route('resource.store'), { preserveScroll: true, onSuccess: () => closeModal() })

// axios JSON (for API endpoints / file uploads as base64)
const { data } = await axios.post(route('resource.store'), { field: value, photo_base64: dataUri })

// NEVER use FormData with multipart/form-data — Cloudflare WAF will block it
```

### Locale & Currency
```js
Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
```

### Pagination (local)
```js
const PER_PAGE = 15
const currentPage = ref(1)
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return filtered.value.slice(start, start + PER_PAGE)
})
```

### Tailwind Conventions
- Input: `rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full`
- Primary button: `bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium`
- Table/section headers: `text-xs font-semibold text-slate-500 uppercase tracking-wide`

---

## Modules Quick Reference
| Module | Key Routes | Controller / Notes |
|---|---|---|
| HR Leave | `hr.leave.*` | `HR\LeaveApplicationController` — 3-stage approval, auto DTR sync |
| HR Employees | `hr.employees.*` | `UserController` |
| DTR / Attendance | `hr.dtr.*` | `HR\DtrRecordController` — travel flag, gate pass deduction |
| WFH Attendance | `hr.wfh.*` | `WFHAttendanceController` — photos stored in S3, served via proxy |
| Payroll | `payroll.*` | `Payroll\PayrollRunController` |
| IPCR / PMS | `ipcr.*`, `pms.*` | `IPCRController`, `PMSController` |
| Faculty Loading | `faculty-loading.*` | `FacultyLoading\*` — uses `SchoolYear` model |
| Class Record | `class-records.*` | `ClassRecord\*` — locked by SchoolYear, CSV bulk import |
| CSM Feedback | `csm.*` | Polymorphic — `csm_responses` with `respondable_type/id` |
| PDS | `pds.*` | Includes Work Experience Sheet (WES) tab |
| Recruitment | `recruitment.*` | `Recruitment\*` |
| SALN | `saln.*` | `SALN\*` |
| Users (Admin) | `users.*` | `UserController` |
| IT Job Requests | `it-job-requests.*` | 3-day filing rule for Technical Assistance on Events |
| Facility Requests | `facility-requests.*` | 3-day filing rule |
| Official Issuances | `issuances.*` | Tiptap editor, TCPDF+FPDI scan stamping, QR code, PIN signing, queue-based PDF+email, `IssuancePermissionSeeder` |
| Document Tracking | `document-tracking.*` | `DocumentTrackingController` — internal/external docs, Google Drive attachments, routing templates (sequential/parallel/manual) |
| Developer Info | `GET /developer` | Public page (no auth) — `Developer.vue`; profiles for Junlou Tordos + Michael Francisco |

---

## Module Deep Notes

### WFH Module
- Time-in/out photos: captured as base64 data URI from camera → sent as JSON → decoded and stored in S3
- Accomplishment photos: same base64 pattern (Cloudflare blocks multipart)
- Photo proxy: `GET /hr/wfh/photo/{fileId}` — authenticates to S3 via SDK, serves privately
- File ID format: `s3.<base64url-encoded-s3-key>` — distinguished from legacy Google Drive IDs
- Route regex: `[a-zA-Z0-9_.=-]+` — the dot is required for the `s3.` prefix

### Class Record Module
- `GradeComputationService` — pure PHP, no DB calls; JS mirror in `resources/js/Utils/ClassRecord/gradeUtils.js`
- Running grade: Q2–Q4 uses `floor((current × 2/3) + (previous × 1/3))` — floor, not round
- School year lock: records from past `SchoolYear` are fully read-only; guard on all editing endpoints
- CSV import: parsed client-side via FileReader → JSON POST → `students/import` endpoint; avoids Cloudflare WAF
- `school_year_id` FK on `class_records` → `school_years.id`; backfilled by matching `school_years.name`
- At-risk row highlights (red/orange/amber) in `ScoreGrid.vue` based on running grade
- Final annual grades tab: `ClassRecordFinalGradeController` — per-student Q1–Q4 GEs + annual average
- Copy assessments from previous quarter: `ClassRecordAssessmentController` copy endpoint
- PDF export: `ClassRecordPdfService` — A3 landscape via mPDF, stanine legend footer
- Teacher notified (bell + email via `ClassRecordCheckedMail`) when admin marks record checked

### Profile Module
- Route: `GET /profile` → `profile.edit`, `PATCH /profile` → `profile.update`
- Editable fields: `name`, `specialization`, `profile_photo_base64` / `profile_photo_mime`
- Photo stored at S3 `profile_pictures/{user_id}_{time}.{ext}`; old photo deleted on update
- Email/password not user-editable (email = HR-managed; password = Google OAuth)
- Profile panel: slide-in `ProfileEditModal.vue` triggered by avatar click
- **Gotcha:** `$user->division` returns a legacy string column — use `Division::find($user->division_id)` explicitly, NOT `$user->load(['division'])` (FK vs legacy column conflict)
- **Gotcha:** `divisions` table uses `division_name` column, NOT `name`
- **Gotcha:** `Storage::disk('s3')->temporaryUrl()` fails in production — use `storageUrl()` composable (serves via `/media/` proxy)

### Mail
- Provider: Gmail SMTP (`smtp.gmail.com:587`, TLS)
- From: `portal@crc.pshs.edu.ph` / `PSHS-CRC MIS`
- Credentials stored in SSM Parameter Store (`/crcmis/prod/MAIL_*`)
- App password (not Google account password) — 16-character app-specific password

### DB Backup (Cron)
- Runs via cron inside the container (supervisord manages cron service)
- `exec()` calls `mysqldump` then `gzip -f` — do NOT disable `exec` in PHP
- Dumps to `sys_get_temp_dir()` temp file (cleaned in `finally`) — no `storage_path` local copy
- Compressed backup (~3-5MB) uploaded to Google Drive using service account
- Google credentials fetched from Secrets Manager at container startup
- `BackupVerify` command checks Google Drive for a backup within the last 25h (no local files to check)
- Schedule lives in `routes/console.php` (canonical); `app/Console/Kernel.php` must not duplicate entries
- Windows: 06:00 PHT (backup), 06:30 PHT (verify) — added May 2026

---

## Security Hardening (Applied May 2026)
| Item | Status |
|---|---|
| CloudTrail audit logging | Enabled → `crcmis-cloudtrail-logs` S3 bucket |
| ECR scan-on-push | Enabled |
| S3 IAM policy | Scoped to `crcmis-mis-storage` only (removed AmazonS3FullAccess) |
| `/etc/environment` | chmod 600 (was world-readable, contained all secrets) |
| Google Drive key | Moved from env var to AWS Secrets Manager |
| nginx security headers | HSTS, X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy |
| nginx rate limiting | Login: 10 req/min; PHP: 60 req/min |
| PHP hardening | max_execution_time=120, allow_url_fopen=Off, open_basedir, disable_functions |
| Queue worker | Runs as `www-data` (not root) |
| RDS | Encrypted at rest (KMS), single-AZ, deletion protection ON |
| Mail | Configured — Gmail SMTP via SSM-stored app password |

---

## Do's ✅
- Eager load relations to avoid N+1 queries
- Use named routes with `route()` helper
- Use `back()->with('success', ...)` for post-mutation redirects
- Check `status <> 'inactive'` when querying active users/records
- Use `useSubmit` composable for non-Inertia form submissions
- Philippine locale for all dates and currency formatting
- Use `SalarySchedule::where('is_current', true)` for current salary data
- Use `SchoolYear::where('is_current', true)->first()` for current school year
- Send files as base64 JSON — never multipart/form-data
- Use `Storage::disk('s3')` for all file operations

## Don'ts ❌
- **Never** use `FormData` / `multipart/form-data` for file uploads — Cloudflare WAF blocks it (403)
- **Never** use `Storage::disk('public')` — sets ACL that S3 blocks silently
- **Never** use `storage_path('app/tmp')` for mPDF — use `sys_get_temp_dir()`
- Never use `app` as Docker service name — always `php`
- Never create Blade views for new pages — always Inertia
- Never use `Auth::user()->role_id` directly — use `hasRole()` / `hasPermission()`
- Never add TypeScript or `@ts-check` to Vue files — project is plain JS
- Never create README/doc files unless explicitly asked
- Never use `git add -A` or `git add .` — stage files by name
- Never force push to `main`
- Don't add error handling for impossible scenarios
- Don't design for hypothetical future requirements
- Don't upgrade to Laravel 13 yet — wait until Laravel 12 nears EOL (~early 2027); `maatwebsite/excel` and other packages may not be compatible
- Never use `new DateTime()` with Eloquent date-cast attributes — use `Carbon::parse($value)->format('Y-m-d')` (PHP 8 type coercion causes silent 0 values)
