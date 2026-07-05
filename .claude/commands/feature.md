Full-cycle feature development for BugSayMis — analyze, plan, get approval, implement, verify, deploy.

Usage: /feature <what to build or change>

## Step 1 — Analyze first
Read all relevant files: controllers, models, services, routes, Vue pages.
Check production data if the feature depends on live schema or real records:
```bash
TASK=$(aws ecs list-tasks --cluster crcmis-prod --query 'taskArns[0]' --output text)
aws ecs execute-command --cluster crcmis-prod --task $TASK --container nginx --interactive \
  --command "env HOME=/tmp php /var/www/artisan tinker --execute='...'"
```

## Step 2 — Present plan, wait for approval
Write a concise analysis of the current state and what needs to change.
Present a numbered implementation plan with specific files.
**Do not write any code until the user says "approved".**

## Step 3 — Implement in order
1. **Migration** (if schema changes): `YYYY_MM_DD_HHMMSS_description.php` — always write `down()`
2. **Model(s)**: update `$fillable`, `$casts`, relationships
3. **Service(s)**: business logic in `app/Services/<Namespace>/`
4. **Controller**: thin — delegates to services, returns `back()->with('success', ...)` or `Inertia::render(...)`
5. **Route**: add to the correct route file (`routes/web.php`, `routes/faculty-loading.php`, etc.)
6. **Vue page**: `<script setup>`, `useForm()` from Inertia, Heroicons from `@heroicons/vue/24/outline`

## Step 4 — Verify
```bash
# Lint all modified PHP files
php -l <file>

# Run migration in dev
cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c \
  "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/<file>"

# Build frontend if Vue files changed
npm run build
```

## Step 5 — Commit and deploy
Stage files by name (never `git add -A`), commit with Co-Authored-By, then:
```bash
git checkout main && git merge junlou && git push origin main && git checkout junlou
```

## Key constraints
- Never write code before approval
- Never `git add -A` or `git add .` — stage by name
- File uploads: base64 JSON only — never `FormData` / `multipart/form-data` (Cloudflare WAF blocks it)
- Storage: `Storage::disk('s3')` only — never `disk('public')`
- Docker service name: `php` — never `app`
- No TypeScript in Vue files
- Eager load relations — avoid N+1
- `sys_get_temp_dir()` for mPDF temp files — never `storage_path()`
