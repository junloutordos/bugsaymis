<script setup>
import { ref, computed, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import {
  MagnifyingGlassIcon, ChevronRightIcon, ChevronDownIcon,
  CircleStackIcon, CommandLineIcon, KeyIcon, CodeBracketIcon,
  ServerIcon, ClipboardDocumentListIcon, RocketLaunchIcon,
  ClockIcon, TableCellsIcon, CubeTransparentIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  routes:      Array,
  schema:      Array,
  permissions: Array,
  stats:       Object,
})

// ── Navigation ─────────────────────────────────────────────────────────────
const activeSection = ref('overview')
const sections = [
  { id: 'overview',     label: 'System Overview',    icon: CubeTransparentIcon },
  { id: 'modules',      label: 'Module Reference',   icon: ClipboardDocumentListIcon },
  { id: 'routes',       label: 'Routes / API',        icon: CommandLineIcon },
  { id: 'schema',       label: 'Database Schema',    icon: CircleStackIcon },
  { id: 'permissions',  label: 'Permissions',         icon: KeyIcon },
  { id: 'conventions',  label: 'Code Conventions',   icon: CodeBracketIcon },
  { id: 'infra',        label: 'Infrastructure',      icon: ServerIcon },
  { id: 'changelog',    label: 'Changelog',           icon: ClockIcon },
]

// ── Global search ──────────────────────────────────────────────────────────
const search = ref('')

// ── Routes section ─────────────────────────────────────────────────────────
const routeSearch  = ref('')
const routeMethod  = ref('')
const routePage    = ref(1)
const ROUTE_PER    = 20

const filteredRoutes = computed(() => {
  const q = routeSearch.value.toLowerCase()
  const m = routeMethod.value
  return (props.routes ?? []).filter(r => {
    if (m && !r.methods.includes(m)) return false
    if (!q) return true
    return r.uri.toLowerCase().includes(q)
        || r.name.toLowerCase().includes(q)
        || r.controller.toLowerCase().includes(q)
  })
})
const routeTotalPages = computed(() => Math.max(1, Math.ceil(filteredRoutes.value.length / ROUTE_PER)))
const pagedRoutes     = computed(() => {
  const s = (routePage.value - 1) * ROUTE_PER
  return filteredRoutes.value.slice(s, s + ROUTE_PER)
})
watch([routeSearch, routeMethod], () => { routePage.value = 1 })

function methodBadgeColor(method) {
  const map = { GET: 'green', POST: 'blue', PUT: 'amber', PATCH: 'orange', DELETE: 'red' }
  return map[method] ?? 'slate'
}

// ── Schema section ─────────────────────────────────────────────────────────
const tableSearch  = ref('')
const expandedTable = ref(null)

const filteredTables = computed(() => {
  const q = tableSearch.value.toLowerCase()
  if (!q) return props.schema ?? []
  return (props.schema ?? []).filter(t =>
    t.name.toLowerCase().includes(q) ||
    t.columns.some(c => c.name.toLowerCase().includes(q))
  )
})

function toggleTable(name) {
  expandedTable.value = expandedTable.value === name ? null : name
}

function keyBadge(key) {
  if (key === 'PRI') return 'amber'
  if (key === 'MUL') return 'blue'
  if (key === 'UNI') return 'purple'
  return 'slate'
}

// ── Permissions section ────────────────────────────────────────────────────
const permSearch = ref('')
const filteredPerms = computed(() => {
  const q = permSearch.value.toLowerCase()
  if (!q) return props.permissions ?? []
  return (props.permissions ?? []).filter(g =>
    g.group.toLowerCase().includes(q) ||
    g.permissions.some(p => p.toLowerCase().includes(q))
  )
})

// ── Module reference data ──────────────────────────────────────────────────
const modules = [
  {
    name: 'HR — Leave Applications',
    route: 'hr.leave.*',
    controller: 'HR\\LeaveApplicationController',
    models: ['HR\\LeaveApplication', 'User', 'Division'],
    permissions: ['hr.leave.view', 'hr.leave.apply', 'hr.leave.approve'],
    description: 'Manages CSC Form No. 6 leave requests across a 3-stage approval chain: Division Chief → HR Officer. Auto-syncs to DTR on approval. Supports all CSC leave types.',
    workflow: 'Employee files → Division Chief forwards → HR Officer approves/rejects → DTR auto-updated',
    rules: ['Work week only (Mon–Fri)', 'Requires sufficient leave balance', 'Approved leaves deduct from DTR automatically'],
  },
  {
    name: 'HR — DTR / Attendance',
    route: 'hr.dtr.*',
    controller: 'HR\\DtrRecordController',
    models: ['HR\\DtrRecord', 'User'],
    permissions: ['hr.dtr.view', 'hr.dtr.manage'],
    description: 'Daily Time Record tracking. Handles biometric sync, travel flag, gate pass deduction. Monthly DTR generation for payroll.',
    workflow: 'Biometric → sync → DTR record → monthly summary → payroll input',
    rules: ['Travel flag excludes days from attendance', 'Gate pass reduces work hours by duration', 'Approved leaves auto-populate DTR'],
  },
  {
    name: 'HR — WFH Attendance',
    route: 'hr.wfh.*',
    controller: 'WFHAttendanceController',
    models: ['WFHAttendance', 'AccomplishmentPhoto'],
    permissions: ['hr.wfh.view', 'hr.wfh.submit'],
    description: 'Work-From-Home attendance with photo-verified time-in/out. Photos captured as base64 from camera → decoded → stored in private S3. Served through /hr/wfh/photo/{fileId} proxy.',
    workflow: 'Camera capture → base64 JSON → S3 → proxy URL',
    rules: ['All photos via base64 JSON (Cloudflare blocks multipart)', 'S3 key prefix: s3.<base64url-encoded-key>'],
  },
  {
    name: 'Payroll',
    route: 'payroll.*',
    controller: 'Payroll\\PayrollRunController',
    models: ['Payroll\\PayrollRun', 'Payroll\\PayrollItem', 'SalarySchedule'],
    permissions: ['payroll.view', 'payroll.manage', 'payroll.cashier'],
    description: 'Full payroll processing: salary computation from SSL schedule, deductions (GSIS, PhilHealth, Pag-IBIG, withholding tax), per-run PDF payslips, cashier disbursement workflow.',
    workflow: 'Run creation → auto-compute from SSL → review → cashier batch → PDF generation',
    rules: ['Uses SalarySchedule where is_current=true', 'Per-batch cashier items for disbursement tracking'],
  },
  {
    name: 'IPCR / PMS',
    route: 'ipcr.*|pms.*',
    controller: 'IPCRController, PMSController',
    models: ['IPCR', 'PMSTarget'],
    permissions: ['ipcr.view', 'ipcr.submit', 'ipcr.approve'],
    description: 'Individual Performance Commitment and Review (IPCR) for all employee levels. Targets, accomplishments, ratings, PMT review cycle.',
    workflow: 'Employee sets targets → accomplishments entered → DC review → PMT approval → final rating',
    rules: [],
  },
  {
    name: 'Faculty Loading',
    route: 'faculty-loading.*',
    controller: 'FacultyLoading\\*',
    models: ['FacultyLoading\\SchoolYear', 'FacultyLoading\\Load'],
    permissions: ['faculty_loading.view', 'faculty_loading.manage'],
    description: 'AI-assisted schedule generation, overload computation, designation management. Locked by SchoolYear.is_current.',
    workflow: 'School year setup → subject assignment → schedule generation → overload computation',
    rules: ['Past school years are fully read-only', 'Overload pay linked to SstPosition model'],
  },
  {
    name: 'Class Records',
    route: 'class-records.*',
    controller: 'ClassRecord\\*',
    models: ['ClassRecord\\ClassRecord'],
    permissions: ['class-records.view', 'class-records.manage'],
    description: 'Grade recording with running grade computation. Q2–Q4 uses floor((current × 2/3) + (previous × 1/3)). CSV bulk import parsed client-side to avoid Cloudflare WAF.',
    workflow: 'Create record → enter grades → compute running → export',
    rules: ['floor() not round() for running grade', 'Locked for past school years', 'CSV import via JSON POST'],
  },
  {
    name: 'IT Job Requests',
    route: 'jobrequests.*',
    controller: 'ITJobRequestController',
    models: ['ITJobRequest', 'ITJRTrackingLog'],
    permissions: ['it.view', 'it.manage'],
    description: 'Technical assistance requests with 3-stage approval (Division Chief → OCD → MIS). Priority queue system, MIS assessment modal, PDF generation, CSM feedback integration.',
    workflow: 'Submit → DC approve → OCD approve → MIS assess → MIS acts → User confirms → CSM rating',
    rules: ['Technical Assistance on Events: 3-day filing rule', 'Priority: urgent > high > normal > low'],
  },
  {
    name: 'Document Tracking',
    route: 'document-tracking.*',
    controller: 'DocumentTrackingController, DocumentTypeController',
    models: ['Document', 'DocumentRouting', 'DocumentTypeRoutingStep'],
    permissions: ['documents.view', 'documents.log', 'documents.review', 'documents.route'],
    description: 'Tracks both internal and external documents. External: Records logs → OCD reviews → routes to offices. Scans uploaded as base64 → Google Drive. Template-based routing chains (sequential/parallel/manual).',
    workflow: 'External: Records logs + scan → OCD reviews → routes to offices. Internal: creator → template chain',
    rules: ['Scans go to Google Drive (dts_folder_id)', 'Routing steps configured per document type', 'office + assigned_user per step'],
  },
  {
    name: 'Recruitment',
    route: 'recruitment.*',
    controller: 'Recruitment\\*',
    models: ['Recruitment\\JobPosting', 'Recruitment\\Application'],
    permissions: ['recruitment.view', 'recruitment.manage'],
    description: 'End-to-end recruitment: job postings, applications, interview scheduling, placement approval.',
    workflow: 'Post vacancy → applications → shortlisting → interview → placement',
    rules: [],
  },
  {
    name: 'SALN',
    route: 'saln.*',
    controller: 'SALN\\*',
    models: ['SALN\\Saln'],
    permissions: ['saln.view', 'saln.submit'],
    description: 'Statement of Assets, Liabilities, and Net Worth. Annual submission, PDF export.',
    workflow: 'Employee fills → submits → HR reviews',
    rules: ['Follows CSC SALN form requirements'],
  },
  {
    name: 'PDS',
    route: 'pds.*',
    controller: 'PDSController',
    models: ['User'],
    permissions: ['hr.employees.view'],
    description: 'Personal Data Sheet (CSC Form 212 Revised 2025). Includes Work Experience Sheet (WES) tab. Employee self-service with HR review.',
    workflow: 'Employee fills → saves → HR verifies',
    rules: [],
  },
  {
    name: 'Requests (Vehicle, Facility, Service, Messengerial)',
    route: 'vehicle-requests.*|facility-requests.*|service-requests.*|messengerial.*',
    controller: 'VehicleRequestController, FacilityRequestController, ServiceRequestController, MessengerialController',
    models: ['VehicleRequest', 'FacilityRequest', 'ServiceRequest', 'MessengerialRequest'],
    permissions: ['vehicles.view', 'facilities.view'],
    description: 'Campus service requests with approval workflows. Facility and vehicle requests require 3-day advance filing. Messengerial has transmittal slip PDF.',
    workflow: 'Request → DC approve → OCD/FAD approve → fulfillment',
    rules: ['Facility/Vehicle: 3-day minimum advance filing', 'Messengerial: signature on transmittal (not in "Received By")'],
  },
  {
    name: 'Chat',
    route: 'chat.*',
    controller: 'ChatController',
    models: ['Chat', 'Message'],
    permissions: [],
    description: 'Real-time messaging using Laravel Echo + Soketi (Pusher protocol). Falls back to HTTP polling when Cloudflare blocks WebSocket upgrades. Available to all users.',
    workflow: 'WS connect → Soketi → Echo → Vue component',
    rules: ['Cloudflare blocks WS upgrades — HTTP polling fallback is normal', 'Initials avatar (not photos)'],
  },
  {
    name: 'Notifications (Bell + Web Push)',
    route: 'api/notifications.*|api/push-subscriptions',
    controller: 'NotificationController, PushSubscriptionController',
    models: ['Notification', 'PushSubscription'],
    permissions: [],
    description: 'Two-layer notification system: in-app bell (database + Soketi broadcast) and Web Push (FCM via minishlink/web-push, VAPID keys). Service worker: atlas-sw.js.',
    workflow: 'Action → NotificationService::notifyUser() → DB + Soketi + FCM',
    rules: ['VAPID_PUBLIC_KEY baked into Vite bundle at build time', 'atlas-sw.js handles push events (not sw.js)'],
  },
]

// ── Changelog data ─────────────────────────────────────────────────────────
const changelog = [
  {
    version: '2.5.0',
    date: 'May 2026',
    type: 'feat',
    items: [
      'Technical Documentation module (this page)',
      'Comprehensive Profile module — base64 photo upload, employment details',
      'Document Tracking System — internal + external, Google Drive scan storage, routing templates',
      'Push notifications (Web Push via FCM + VAPID) + in-app bell',
      'IT Job Request priority queue and MIS assessment modal',
    ],
  },
  {
    version: '2.4.0',
    date: 'May 2026',
    type: 'feat',
    items: [
      'AWS infrastructure security hardening (A− rating)',
      'CloudTrail audit logging, ECR scan-on-push, scoped S3 IAM',
      'nginx rate limiting (login: 10/min, API: 60/min)',
      'PHP security hardening (open_basedir, disable_functions, max_execution_time)',
      'Google Drive credentials moved to AWS Secrets Manager',
    ],
  },
  {
    version: '2.3.0',
    date: 'May 2026',
    type: 'feat',
    items: [
      'WFH photos migrated from Google Drive to private S3 with proxy route',
      'Payroll Cashier disbursement workflow',
      'Messengerial signature fix (removed division chief from "Received By")',
      'Gate pass OCD division bypass for approval',
      'MIS Dashboard personnel workload normalization',
    ],
  },
  {
    version: '2.2.0',
    date: 'April 2026',
    type: 'feat',
    items: [
      'Class Record module with running grade computation',
      'CSM Feedback module (polymorphic respondable)',
      'PDS Work Experience Sheet (WES) tab',
      'DTR travel flag and gate pass integration',
    ],
  },
  {
    version: '2.1.0',
    date: 'March 2026',
    type: 'feat',
    items: [
      'Production deployment to AWS ECS Fargate',
      'Cloudflare WAF + ALB TLS 1.3',
      'GitHub Actions CI/CD pipeline',
      'S3 private bucket with /media/ proxy',
    ],
  },
]

function changelogBadgeColor(type) {
  const map = { feat: 'indigo', fix: 'red', ops: 'slate' }
  return map[type] ?? 'indigo'
}

// ── Conventions data (kept in script to avoid template parser issues) ─────
const conventions = [
  {
    title: 'PHP / Laravel',
    items: [
      'Thin controllers — move business logic to Service classes in app/Services/',
      "Always eager load to avoid N+1: User::with(['role', 'division', 'office'])",
      'Permission middleware: permission:a|b (ANY) · permission:a,b (ALL)',
      "Soft delete = set status = 'inactive' (no Laravel SoftDeletes trait)",
      "After mutation: return back()->with('success', 'Message.')",
      "After create: return redirect()->route('resource.index')->with('success', '...')",
      'mPDF tempDir must use sys_get_temp_dir() — never storage_path()',
      "File uploads: base64 decode → Storage::disk('s3')->put() — never multipart",
    ],
  },
  {
    title: 'Vue / Frontend',
    items: [
      'Always use script setup (Composition API) — no Options API',
      'Icons only from @heroicons/vue/24/outline',
      'Form submissions: useForm() from @inertiajs/vue3 or axios JSON',
      "Currency: toLocaleString('en-PH', { minimumFractionDigits: 2 })",
      "Dates: toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })",
      'Pagination: PER_PAGE = 15, local computed slice',
      'No TypeScript, no .ts files, no @ts-check',
      'No Vuex / Pinia — use Inertia props + local ref()',
    ],
  },
  {
    title: 'Database / Migrations',
    items: [
      'Filename: YYYY_MM_DD_HHMMSS_description_snake_case.php',
      'Always write down() to reverse the migration',
      "Add columns with ->after('existing_column')",
      'Run via: docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate"',
      'Never modify an existing migration — add a new one',
      'Foreign keys: use constrained() with appropriate onDelete behavior',
    ],
  },
  {
    title: 'Git Workflow',
    items: [
      'Stage specific files by name — never git add -A or git add .',
      'Commit messages: imperative mood, short summary',
      'Deploy: git checkout main && git merge junlou && git push origin main',
      'Never force push to main',
      'Never skip hooks (--no-verify)',
    ],
  },
]

const infraBlocks = [
  {
    title: 'CI/CD Pipeline',
    icon: RocketLaunchIcon,
    colorCls: 'text-indigo-500',
    items: [
      'Push to main → GitHub Actions triggers',
      'docker buildx build with VITE_* as --build-arg',
      'Pushes to ECR with immutable tag (commit SHA)',
      'Updates ECS task definition with new image',
      'ECS rolling deployment (2 tasks, circuit breaker ON)',
      'Migrations run automatically on container start (docker-entrypoint.sh)',
    ],
  },
  {
    title: 'Secrets Management',
    icon: KeyIcon,
    colorCls: 'text-amber-500',
    items: [
      'SSM Parameter Store: /crcmis/prod/* (DB, mail, Redis, VAPID, S3)',
      'AWS Secrets Manager: crcmis/google-drive-credentials (service account JSON)',
      'VAPID public key baked into Vite bundle as VITE_VAPID_PUBLIC_KEY',
      'Never put secrets in code or plaintext env vars',
      'ECS task role pulls SSM params at startup via docker-entrypoint.sh',
    ],
  },
  {
    title: 'Database Operations',
    icon: CircleStackIcon,
    colorCls: 'text-emerald-500',
    items: [
      'RDS MySQL 8.0, Multi-AZ, encrypted at rest (KMS), deletion protection ON',
      'Automated backups: 7-day retention',
      'Daily backup cron: mysqldump → gzip → Google Drive (service account)',
      'Production Artisan: aws ecs execute-command --cluster crcmis-prod',
      "tinker in prod: env HOME=/tmp php /var/www/artisan tinker --execute='...'",
      'Restore: base64 exfiltrate from ECS exec session',
    ],
  },
  {
    title: 'PHP Security Config',
    icon: ServerIcon,
    colorCls: 'text-red-500',
    items: [
      'open_basedir = /var/www:/tmp:/usr/local/etc/php',
      'max_execution_time = 120 (scripts time out after 2 min)',
      'allow_url_fopen = Off (no remote URL fetching)',
      'disable_functions: system, shell_exec, passthru, proc_open, popen, pcntl_exec',
      'exec() is ALLOWED (needed for mysqldump backup cron)',
      'session.cookie_httponly = 1, cookie_secure = 1, use_strict_mode = 1',
    ],
  },
]

// ── Helpers ────────────────────────────────────────────────────────────────
function fmtType(t) {
  const map = { varchar: 'varchar', int: 'int', bigint: 'bigint', text: 'text', timestamp: 'ts', tinyint: 'bool', enum: 'enum', json: 'json', date: 'date', decimal: 'decimal' }
  for (const [k, v] of Object.entries(map)) if (t.startsWith(k)) return v
  return t
}
</script>

<template>
  <Head title="Technical Documentation" />
  <AdminLayout title="Technical Documentation">
    <div class="flex gap-0 min-h-screen -m-4 md:-m-6">

      <!-- ── Left Sidebar ─────────────────────────────────────────────────── -->
      <aside class="hidden lg:flex flex-col w-56 shrink-0 border-r border-slate-200 bg-slate-50 pt-4 pb-8 sticky top-0 max-h-screen overflow-y-auto">
        <div class="px-4 mb-4">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Atlas v2.5</p>
          <p class="text-xs text-slate-500 mt-0.5">Technical Reference</p>
        </div>

        <!-- Global search -->
        <div class="px-3 mb-3">
          <div class="relative">
            <MagnifyingGlassIcon class="absolute left-2.5 top-2 h-3.5 w-3.5 text-slate-400" />
            <input v-model="search" type="text" placeholder="Search…"
              class="w-full pl-8 pr-2 py-1.5 text-xs border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-indigo-500 bg-white" />
          </div>
        </div>

        <nav class="flex-1 px-2 space-y-0.5">
          <button v-for="s in sections" :key="s.id"
            @click="activeSection = s.id"
            class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-left text-sm transition-colors"
            :class="activeSection === s.id
              ? 'bg-indigo-600 text-white font-medium'
              : 'text-slate-600 hover:bg-slate-200'">
            <component :is="s.icon" class="h-4 w-4 shrink-0" />
            {{ s.label }}
          </button>
        </nav>

        <!-- Stats -->
        <div class="mx-3 mt-4 p-3 bg-white rounded-xl border border-slate-200 space-y-2">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">System Stats</p>
          <div class="grid grid-cols-2 gap-1.5 text-center">
            <div class="bg-indigo-50 rounded-lg py-1.5">
              <p class="text-base font-bold text-indigo-700">{{ stats?.routes?.toLocaleString() }}</p>
              <p class="text-[10px] text-slate-500">Routes</p>
            </div>
            <div class="bg-emerald-50 rounded-lg py-1.5">
              <p class="text-base font-bold text-emerald-700">{{ stats?.tables }}</p>
              <p class="text-[10px] text-slate-500">Tables</p>
            </div>
            <div class="bg-amber-50 rounded-lg py-1.5">
              <p class="text-base font-bold text-amber-700">{{ stats?.permissions }}</p>
              <p class="text-[10px] text-slate-500">Permissions</p>
            </div>
            <div class="bg-blue-50 rounded-lg py-1.5">
              <p class="text-base font-bold text-blue-700">{{ stats?.users }}</p>
              <p class="text-[10px] text-slate-500">Users</p>
            </div>
          </div>
        </div>
      </aside>

      <!-- ── Main Content ─────────────────────────────────────────────────── -->
      <main class="flex-1 overflow-x-hidden p-5 md:p-8 space-y-6">

        <!-- Mobile section tabs -->
        <div class="flex lg:hidden gap-1 overflow-x-auto pb-2">
          <button v-for="s in sections" :key="s.id"
            @click="activeSection = s.id"
            class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap transition-colors shrink-0"
            :class="activeSection === s.id ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600'">
            {{ s.label }}
          </button>
        </div>

        <!-- ══ 1. OVERVIEW ══════════════════════════════════════════════════ -->
        <section v-if="activeSection === 'overview'" class="space-y-6">
          <div>
            <h1 class="text-2xl font-bold text-slate-800">System Overview</h1>
            <p class="text-slate-500 mt-1">Atlas — Centralized MIS of PSHS-CRC</p>
          </div>

          <!-- Stack -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div v-for="item in [
              { label: 'Backend', value: 'Laravel 12 · PHP 8.4', color: 'indigo' },
              { label: 'Frontend', value: 'Vue 3 · Inertia.js 2 · Vite 7', color: 'blue' },
              { label: 'Styling', value: 'Tailwind CSS 3 · Heroicons 2', color: 'cyan' },
              { label: 'Database', value: 'MySQL 8.0 (AWS RDS Multi-AZ)', color: 'emerald' },
              { label: 'Real-time', value: 'Soketi · Laravel Echo · Pusher JS', color: 'violet' },
              { label: 'File Storage', value: 'AWS S3 (Block Public Access ON)', color: 'amber' },
              { label: 'Container', value: 'Docker · AWS ECS Fargate', color: 'orange' },
              { label: 'CI/CD', value: 'GitHub Actions → ECR → ECS', color: 'slate' },
              { label: 'PDF', value: 'mPDF 8 (tempDir = sys_get_temp_dir())', color: 'pink' },
            ]" :key="item.label">
              <AppCard>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">{{ item.label }}</p>
                <p class="text-sm font-medium text-slate-800">{{ item.value }}</p>
              </AppCard>
            </div>
          </div>

          <!-- Architecture -->
          <AppCard title="Production Architecture">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
              <div v-for="row in [
                ['App URL',     'https://mis.crc.pshs.edu.ph'],
                ['CDN / WAF',   'Cloudflare (orange-cloud, WAF active)'],
                ['Load Balancer','crcmis-alb (HTTPS only, TLS 1.3)'],
                ['ECS Cluster', 'crcmis-prod (Fargate, 2 tasks)'],
                ['Container',   'nginx + PHP-FPM + cron + queue (single)'],
                ['ECR Repo',    '971422671747.dkr.ecr.ap-southeast-1.amazonaws.com/crcmis/app'],
                ['RDS',         'crcmis-db-encrypted (Multi-AZ, KMS)'],
                ['Redis',       'crcmis-redis.d8qigv (ElastiCache)'],
                ['S3 Bucket',   'crcmis-mis-storage (ap-southeast-1, private)'],
                ['Secrets',     'SSM Parameter Store /crcmis/prod/*'],
              ]" :key="row[0]"
                class="flex gap-2">
                <span class="text-slate-400 w-32 shrink-0 text-xs font-medium">{{ row[0] }}</span>
                <span class="text-slate-700 font-mono text-xs break-all">{{ row[1] }}</span>
              </div>
            </div>
          </AppCard>

          <!-- Critical Rules -->
          <AppCard title="⚠️ Critical Rules">
            <ul class="space-y-2 text-sm text-slate-600">
              <li class="flex gap-2"><span class="text-danger-600 font-bold shrink-0">✗</span><span><strong>Never</strong> use <code class="bg-slate-100 px-1 rounded text-xs">FormData / multipart/form-data</code> for file uploads — Cloudflare WAF blocks it (403). Use base64 JSON instead.</span></li>
              <li class="flex gap-2"><span class="text-danger-600 font-bold shrink-0">✗</span><span><strong>Never</strong> use <code class="bg-slate-100 px-1 rounded text-xs">Storage::disk('public')</code> — S3 Block Public Access is ON. Always use <code class="bg-slate-100 px-1 rounded text-xs">Storage::disk('s3')</code>.</span></li>
              <li class="flex gap-2"><span class="text-danger-600 font-bold shrink-0">✗</span><span><strong>Never</strong> use <code class="bg-slate-100 px-1 rounded text-xs">storage_path()</code> for mPDF — use <code class="bg-slate-100 px-1 rounded text-xs">sys_get_temp_dir()</code> (open_basedir restriction).</span></li>
              <li class="flex gap-2"><span class="text-danger-600 font-bold shrink-0">✗</span><span><strong>Never</strong> use <code class="bg-slate-100 px-1 rounded text-xs">app</code> as Docker service name — always <code class="bg-slate-100 px-1 rounded text-xs">php</code>.</span></li>
              <li class="flex gap-2"><span class="text-success-600 font-bold shrink-0">✓</span><span>Run Artisan via: <code class="bg-slate-100 px-1 rounded text-xs">docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan ..."</code></span></li>
              <li class="flex gap-2"><span class="text-success-600 font-bold shrink-0">✓</span><span>Deploy: <code class="bg-slate-100 px-1 rounded text-xs">git push origin main</code> → GitHub Actions → ECR → ECS rolling update (~10 min)</span></li>
            </ul>
          </AppCard>
        </section>

        <!-- ══ 2. MODULES ═══════════════════════════════════════════════════ -->
        <section v-if="activeSection === 'modules'" class="space-y-4">
          <h1 class="text-2xl font-bold text-slate-800">Module Reference</h1>
          <p class="text-slate-500 text-sm">{{ modules.length }} modules · click any card to expand</p>

          <AppCard v-for="mod in modules" :key="mod.name" :padded="false">
            <button @click="expandedTable === mod.name ? expandedTable = null : expandedTable = mod.name"
              class="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-slate-50 transition-colors">
              <div>
                <p class="font-semibold text-slate-800">{{ mod.name }}</p>
                <p class="text-xs text-slate-500 mt-0.5 font-mono">{{ mod.controller }}</p>
              </div>
              <component :is="expandedTable === mod.name ? ChevronDownIcon : ChevronRightIcon"
                class="h-4 w-4 text-slate-400 shrink-0" />
            </button>

            <div v-if="expandedTable === mod.name" class="border-t border-slate-100 px-5 py-4 space-y-3 text-sm">
              <p class="text-slate-700">{{ mod.description }}</p>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Route Pattern</p>
                  <code class="text-xs bg-slate-100 px-2 py-1 rounded text-slate-700">{{ mod.route }}</code>
                </div>
                <div>
                  <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Models</p>
                  <div class="flex flex-wrap gap-1">
                    <span v-for="m in mod.models" :key="m"
                      class="text-xs bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded font-mono">{{ m }}</span>
                  </div>
                </div>
              </div>

              <div v-if="mod.workflow">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Workflow</p>
                <p class="text-xs text-slate-600 bg-slate-50 rounded-lg px-3 py-2">{{ mod.workflow }}</p>
              </div>

              <div v-if="mod.permissions.length">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Permissions</p>
                <div class="flex flex-wrap gap-1">
                  <span v-for="p in mod.permissions" :key="p"
                    class="text-xs bg-amber-50 text-amber-700 px-2 py-0.5 rounded font-mono">{{ p }}</span>
                </div>
              </div>

              <div v-if="mod.rules.length">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Business Rules</p>
                <ul class="space-y-1">
                  <li v-for="r in mod.rules" :key="r" class="text-xs text-slate-600 flex gap-1.5">
                    <span class="text-indigo-400 shrink-0">·</span>{{ r }}
                  </li>
                </ul>
              </div>
            </div>
          </AppCard>
        </section>

        <!-- ══ 3. ROUTES ════════════════════════════════════════════════════ -->
        <section v-if="activeSection === 'routes'" class="space-y-4">
          <h1 class="text-2xl font-bold text-slate-800">Routes / API Reference</h1>
          <p class="text-slate-500 text-sm">{{ routes?.length?.toLocaleString() }} total routes · auto-generated</p>

          <!-- Filters -->
          <div class="flex flex-col sm:flex-row gap-2">
            <div class="relative flex-1">
              <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
              <input v-model="routeSearch" type="text" placeholder="Filter by URI, name, or controller…"
                class="w-full pl-9 pr-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <select v-model="routeMethod"
              class="px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="">All Methods</option>
              <option>GET</option><option>POST</option><option>PUT</option>
              <option>PATCH</option><option>DELETE</option>
            </select>
          </div>

          <p class="text-xs text-slate-400">Showing {{ filteredRoutes.length.toLocaleString() }} routes</p>

          <!-- Table -->
          <AppTable :is-empty="!pagedRoutes.length" :skeleton-cols="5">
            <template #head>
              <tr>
                <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Method</th>
                <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">URI</th>
                <th class="hidden md:table-cell px-3 py-2.5 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Name</th>
                <th class="hidden lg:table-cell px-3 py-2.5 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Controller</th>
                <th class="hidden xl:table-cell px-3 py-2.5 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Middleware</th>
              </tr>
            </template>

            <tr v-for="r in pagedRoutes" :key="r.name + r.uri" class="hover:bg-slate-50">
              <td class="px-3 py-2 text-xs">
                <div class="flex flex-wrap gap-1">
                  <AppBadge v-for="m in r.methods" :key="m" :color="methodBadgeColor(m)">{{ m }}</AppBadge>
                </div>
              </td>
              <td class="px-3 py-2 text-xs font-mono text-slate-700 max-w-[200px] truncate">{{ r.uri }}</td>
              <td class="hidden md:table-cell px-3 py-2 text-xs text-slate-500 max-w-[160px] truncate">{{ r.name }}</td>
              <td class="hidden lg:table-cell px-3 py-2 text-xs text-slate-500 max-w-[200px] truncate font-mono">{{ r.controller }}</td>
              <td class="hidden xl:table-cell px-3 py-2">
                <div class="flex flex-wrap gap-0.5">
                  <span v-for="mw in r.middleware.slice(0, 3)" :key="mw"
                    class="bg-slate-100 text-slate-500 px-1 py-0.5 rounded text-[10px] font-mono">{{ mw }}</span>
                </div>
              </td>
            </tr>

            <template #empty>
              <EmptyState title="No routes match your filters" />
            </template>

            <template #footer>
              <PaginationControl
                :current-page="routePage"
                :total-pages="routeTotalPages"
                :total="filteredRoutes.length"
                @prev="routePage--"
                @next="routePage++"
                @page="routePage = $event"
              />
            </template>
          </AppTable>
        </section>

        <!-- ══ 4. DATABASE SCHEMA ═══════════════════════════════════════════ -->
        <section v-if="activeSection === 'schema'" class="space-y-4">
          <h1 class="text-2xl font-bold text-slate-800">Database Schema</h1>
          <p class="text-slate-500 text-sm">{{ schema?.length }} tables · auto-generated from information_schema</p>

          <div class="relative">
            <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
            <input v-model="tableSearch" type="text" placeholder="Search tables or columns…"
              class="w-full pl-9 pr-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>

          <div class="space-y-2">
            <AppCard v-for="table in filteredTables" :key="table.name" :padded="false">
              <button @click="toggleTable(table.name)"
                class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-slate-50 transition-colors">
                <div class="flex items-center gap-3">
                  <TableCellsIcon class="h-4 w-4 text-slate-400 shrink-0" />
                  <span class="font-mono text-sm font-semibold text-slate-800">{{ table.name }}</span>
                  <span class="text-xs text-slate-400">{{ table.columns.length }} cols</span>
                </div>
                <component :is="expandedTable === table.name ? ChevronDownIcon : ChevronRightIcon"
                  class="h-4 w-4 text-slate-400 shrink-0" />
              </button>

              <div v-if="expandedTable === table.name" class="border-t border-slate-100">
                <AppTable :card="false" :is-empty="!table.columns.length">
                  <template #head>
                    <tr>
                      <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Column</th>
                      <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Type</th>
                      <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Null</th>
                      <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Key</th>
                      <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">FK</th>
                    </tr>
                  </template>

                  <tr v-for="col in table.columns" :key="col.name"
                    class="hover:bg-slate-50"
                    :class="{ 'bg-warning-50/50': col.key === 'PRI' }">
                    <td class="px-3 py-1.5 text-xs font-mono font-medium text-slate-800">{{ col.name }}</td>
                    <td class="px-3 py-1.5 text-xs font-mono text-indigo-700">{{ fmtType(col.type) }}</td>
                    <td class="px-3 py-1.5 text-xs text-slate-400">{{ col.nullable ? 'YES' : '—' }}</td>
                    <td class="px-3 py-1.5">
                      <AppBadge v-if="col.key" :color="keyBadge(col.key)">{{ col.key }}</AppBadge>
                    </td>
                    <td class="px-3 py-1.5 font-mono text-xs text-blue-600">
                      <span v-if="col.fk_table">{{ col.fk_table }}.{{ col.fk_column }}</span>
                    </td>
                  </tr>
                </AppTable>
              </div>
            </AppCard>
          </div>
        </section>

        <!-- ══ 5. PERMISSIONS ═══════════════════════════════════════════════ -->
        <section v-if="activeSection === 'permissions'" class="space-y-4">
          <h1 class="text-2xl font-bold text-slate-800">Permissions Reference</h1>
          <p class="text-slate-500 text-sm">{{ stats?.permissions }} permissions across {{ permissions?.length }} modules · auto-generated</p>

          <div class="relative">
            <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
            <input v-model="permSearch" type="text" placeholder="Search permissions…"
              class="w-full pl-9 pr-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>

          <AppCard>
            <p class="text-xs text-slate-500 mb-3">Usage in code: <code class="bg-slate-100 px-1.5 py-0.5 rounded">$user->hasPermission('module.sub.action')</code> · Middleware: <code class="bg-slate-100 px-1.5 py-0.5 rounded">permission:a|b</code> (ANY) · <code class="bg-slate-100 px-1.5 py-0.5 rounded">permission:a,b</code> (ALL)</p>
            <p class="text-xs text-slate-500">SuperAdmin (<code class="bg-slate-100 px-1.5 py-0.5 rounded">Administrator</code> role) bypasses all permission checks.</p>
          </AppCard>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <AppCard v-for="group in filteredPerms" :key="group.group">
              <p class="font-semibold text-slate-700 mb-2 capitalize flex items-center gap-2">
                <KeyIcon class="h-3.5 w-3.5 text-amber-500" />
                {{ group.group }}
                <span class="text-xs text-slate-400 font-normal">({{ group.permissions.length }})</span>
              </p>
              <div class="flex flex-wrap gap-1">
                <span v-for="p in group.permissions" :key="p"
                  class="text-xs bg-amber-50 text-amber-700 px-2 py-0.5 rounded font-mono">{{ p }}</span>
              </div>
            </AppCard>
          </div>
        </section>

        <!-- ══ 6. CONVENTIONS ═══════════════════════════════════════════════ -->
        <section v-if="activeSection === 'conventions'" class="space-y-5">
          <h1 class="text-2xl font-bold text-slate-800">Code Conventions</h1>

          <AppCard v-for="block in conventions" :key="block.title">
            <h2 class="font-semibold text-slate-700 mb-3 flex items-center gap-2">
              <CodeBracketIcon class="h-4 w-4 text-indigo-500" />
              {{ block.title }}
            </h2>
            <ul class="space-y-1.5">
              <li v-for="item in block.items" :key="item"
                class="text-sm text-slate-600 flex gap-2">
                <span class="text-indigo-400 shrink-0 mt-0.5">·</span>
                <span>{{ item }}</span>
              </li>
            </ul>
          </AppCard>
        </section>

        <!-- ══ 7. INFRASTRUCTURE ════════════════════════════════════════════ -->
        <section v-if="activeSection === 'infra'" class="space-y-5">
          <h1 class="text-2xl font-bold text-slate-800">Infrastructure & Deployment</h1>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <AppCard v-for="block in infraBlocks" :key="block.title">
              <h2 class="font-semibold text-slate-700 mb-3 flex items-center gap-2">
                <component :is="block.icon" class="h-4 w-4 shrink-0" :class="block.colorCls" />
                {{ block.title }}
              </h2>
              <ul class="space-y-1.5">
                <li v-for="item in block.items" :key="item"
                  class="text-sm text-slate-600 flex gap-2">
                  <span class="text-slate-300 shrink-0">·</span>{{ item }}
                </li>
              </ul>
            </AppCard>
          </div>
        </section>

        <!-- ══ 8. CHANGELOG ════════════════════════════════════════════════ -->
        <section v-if="activeSection === 'changelog'" class="space-y-4">
          <h1 class="text-2xl font-bold text-slate-800">Changelog</h1>
          <p class="text-slate-500 text-sm">Release history for Atlas</p>

          <AppCard v-for="release in changelog" :key="release.version">
            <div class="flex items-center gap-3 mb-3">
              <span class="font-mono font-bold text-slate-800 text-base">v{{ release.version }}</span>
              <AppBadge :color="changelogBadgeColor(release.type)">{{ release.type }}</AppBadge>
              <span class="text-sm text-slate-400">{{ release.date }}</span>
            </div>
            <ul class="space-y-1.5">
              <li v-for="item in release.items" :key="item"
                class="text-sm text-slate-600 flex gap-2">
                <span class="text-indigo-400 shrink-0">·</span>{{ item }}
              </li>
            </ul>
          </AppCard>
        </section>

      </main>
    </div>
  </AdminLayout>
</template>
