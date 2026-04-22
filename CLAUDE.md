# BugSayMis — PSHS Caraga Campus Management Information System

## Project Overview
BugSayMis is a comprehensive MIS for the Philippine Science High School – Caraga Region Campus. It covers HR, payroll, faculty loading, performance management (IPCR/PMS), recruitment, library, service requests, SALN, document tracking, and more.

## Tech Stack
| Layer | Technology |
|---|---|
| Backend | Laravel 12, PHP 8.2+ |
| Frontend | Vue 3 (`<script setup>`), Inertia.js 2, Tailwind CSS 3 |
| Build | Vite 7 |
| Database | MySQL 8.0 |
| Real-time | Laravel Echo + Pusher + Soketi |
| PDF | mPDF 8 |
| Icons | Heroicons 2 (`@heroicons/vue/24/outline`) |
| Container | Docker (PHP service = `php`, NOT `app`) |

## Running Artisan Commands
The PHP container path is `/var/www/html/bugsaymis`. Always run artisan like this:
```bash
cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan COMMAND"
```

## Directory Structure
```
app/
  Http/Controllers/         # Root controllers + namespaced: HR/, Payroll/, Recruitment/, FacultyLoading/, SALN/, etc.
  Models/                   # Eloquent models + namespaced: HR/, Payroll/, FacultyLoading/, SALN/
  Services/                 # Business logic: HR/, Payroll/, FacultyLoading/, Recruitment/
  Http/Requests/            # Form Request classes for complex validation
resources/js/
  Pages/                    # Inertia Vue pages (one file per route)
  Components/               # Reusable Vue components (AppTable, AppModal, AppCard, etc.)
  Composables/              # Vue composables (useUsers.js, useIPCR.js, useSubmit.js, etc.)
  Layouts/                  # AdminLayout.vue (primary), AuthenticatedLayout.vue, GuestLayout.vue
database/migrations/        # 290+ migrations — format: YYYY_MM_DD_HHMMSS_description.php
routes/
  web.php                   # Main routes (~900 lines)
  auth.php, chat.php, faculty-loading.php, saln.php
```

## Backend Conventions

### Controllers
- Always return `Inertia::render('Path/To/Page', [...props])` — never blade views
- Thin controllers; heavy logic goes in Service classes
- Authorization: `$this->authorize('permission.string')` or middleware on route

### Permissions & Roles
```php
// Middleware — pipe = ANY, comma = ALL
->middleware('permission:hr.leave.view|hr.leave.approve')  // ANY
->middleware('permission:hr.leave.view,hr.employee.manage') // ALL (both required)

// In code
$user->hasPermission('hr.leave.view')
$user->hasAnyPermission(['hr.leave.view', 'hr.leave.approve'])
$user->isSuperAdmin()   // Administrator role bypasses everything
```
Permission string pattern: `module.submodule.action`
Examples: `hr.leave.view`, `hr.leave.approve`, `hr.employees.manage`, `users.manage`

### Eloquent Patterns
```php
// Always eager load to avoid N+1
User::with(['role', 'division.divisionchief', 'office'])->get()

// Exclude inactive (soft-delete pattern — no Laravel softDeletes, just status column)
->where('status', '<>', 'inactive')
->where('status', 'inactive')  // for inactive-only views

// Redirect after mutation
return back()->with('success', 'Record updated.');
return redirect()->route('hr.leave.index')->with('success', 'Applied.');
```

### Migrations
- Format: `YYYY_MM_DD_HHMMSS_description.php`
- Add columns with `->after('existing_column')`
- Run via Docker artisan (see above)
- Always write a `down()` method

### Key Models
| Model | Key Fields / Notes |
|---|---|
| `User` | `role_id` (legacy), many-to-many `roles`, `division_id`, `office_id`, `salary_grade`, `salary_step`, `status`, `emp_category` |
| `Division` | `division_chief_id`, `status` (active/inactive) |
| `Office` | belongs to `Division` |
| `Role`, `Permission` | RBAC — pivot table `role_user`, `permission_role` |
| `HR\LeaveApplication` | `status`: pending / forwarded / approved / rejected |
| `HR\LeaveCredit` | per user, per year, per leave type |
| `FacultyLoading\SalarySchedule` | `salary_grade`, `step`, `monthly_rate`, `is_current` |

## Frontend Conventions

### Vue Component Pattern
```vue
<script setup>
import { ref, computed, watch } from 'vue'
import { Head, usePage, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { SomeIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ items: Array, pageTitle: { type: String, default: 'Title' } })
</script>

<template>
  <Head :title="props.pageTitle" />
  <AdminLayout :title="props.pageTitle">
    <!-- content -->
  </AdminLayout>
</template>
```

### Forms (Inertia useForm)
```js
const form = useForm({ name: '', status: 'active' })
form.post(route('resource.store'), {
  preserveScroll: true,
  onSuccess: () => closeModal(),
  onError: () => { /* handle */ },
})
// or for updates:
form.patch(route('resource.update', id), { preserveScroll: true })
```

### Locale & Currency
```js
// Philippine Peso
Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

// Philippine date
new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
```

### Pagination Pattern (local)
```js
const PER_PAGE = 15
const currentPage = ref(1)
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return filtered.value.slice(start, start + PER_PAGE)
})
```

### Tailwind Class Conventions
- Form inputs: `rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full`
- Primary button: `bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium`
- Section headers / table headers: `text-xs font-semibold text-slate-500 uppercase tracking-wide`

## Modules Quick Reference
| Module | Key Routes | Controller Namespace |
|---|---|---|
| HR Leave | `hr.leave.*` | `HR\LeaveApplicationController` |
| HR Employees | `hr.employees.*` | `UserController` |
| DTR / Attendance | `hr.dtr.*` | `HR\DtrRecordController` |
| Payroll | `payroll.*` | `Payroll\PayrollRunController` |
| IPCR / PMS | `ipcr.*`, `pms.*` | `IPCRController`, `PMSController` |
| Faculty Loading | `faculty-loading.*` | `FacultyLoading\*` |
| Recruitment | `recruitment.*` | `Recruitment\*` |
| SALN | `saln.*` | `SALN\*` |
| Users (Admin) | `users.*` | `UserController` |
| Requests | `it-job-requests.*`, `vehicle-requests.*`, etc. | Root controllers |

## Do's ✅
- Eager load relations to avoid N+1 queries
- Use named routes with `route()` helper
- Use `back()->with('success', ...)` for post-mutation redirects
- Check `status <> 'inactive'` when querying active users/records
- Use `useSubmit` composable for non-Inertia form submissions
- Philippine locale for all dates and currency formatting
- Use `SalarySchedule::where('is_current', true)` to get current salary data

## Don'ts ❌
- Never use `app` as Docker service name — always `php`
- Never create Blade views for new pages — always Inertia
- Never use `Auth::user()->role_id` directly — use `hasRole()` / `hasPermission()`
- Never add TypeScript or `@ts-check` to Vue files — project is plain JS
- Never create README/doc files unless explicitly asked
- Never use `git add -A` or `git add .` — stage files by name
- Never skip `--no-verify` hooks or force push to main
- Don't add error handling for impossible scenarios
- Don't design for hypothetical future requirements
