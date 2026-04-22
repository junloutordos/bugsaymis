# BugSayMis Coding Standards

## PHP / Laravel

- Controllers must be thin — move business logic to Service classes in `app/Services/`
- Always eager load relations: `User::with(['role', 'division', 'office'])`
- Permission middleware syntax:
  - `permission:a|b` → user needs ANY of a, b
  - `permission:a,b` → user needs ALL of a AND b
- Use `$user->isSuperAdmin()` — never hardcode role IDs
- Soft-delete = set `status = 'inactive'` (no Laravel SoftDeletes trait)
- After mutation: `return back()->with('success', 'Message.')`
- After create/store redirect to index: `return redirect()->route('resource.index')->with('success', '...')`
- Run artisan ONLY via Docker php service — never bare `php artisan`
- Validate ALL user input before touching the database

## Vue / Frontend

- Use `<script setup>` (Composition API) — no Options API
- Import icons only from `@heroicons/vue/24/outline`
- All form submissions use `useForm()` from `@inertiajs/vue3`
- Currency: `toLocaleString('en-PH', { minimumFractionDigits: 2 })`
- Dates: `toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })`
- Pagination: `PER_PAGE = 15`, local computed slice
- No TypeScript, no `.ts` files, no `@ts-check`
- No Vuex / Pinia — use Inertia props + local `ref()`
- No inline `style=""` except in print-specific layouts

## Git

- Stage specific files by name — never `git add -A` or `git add .`
- Commit messages: imperative, short summary + `Co-Authored-By: Claude`
- Never force push to `main`
- Never use `--no-verify`

## Migrations

- Filename: `YYYY_MM_DD_HHMMSS_description_snake_case.php`
- Always write `down()` to reverse the migration
- Add columns with `->after('existing_column')`
- Run via: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/<file>"`
