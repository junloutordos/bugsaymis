<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9pt; color: #1e293b; line-height: 1.5; }

/* ── Cover Page ── */
.cover { text-align: center; padding: 80px 40px; }
.cover-logo { font-size: 11pt; color: #64748b; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 40px; }
.cover-title { font-size: 28pt; font-weight: bold; color: #1e293b; margin-bottom: 8px; }
.cover-sub   { font-size: 13pt; color: #4f46e5; margin-bottom: 40px; }
.cover-meta  { font-size: 9pt; color: #94a3b8; }
.cover-stats { display: table; width: 100%; margin: 40px auto; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
.cover-stat  { display: table-cell; text-align: center; padding: 16px; border-right: 1px solid #e2e8f0; }
.cover-stat:last-child { border-right: none; }
.stat-num    { font-size: 20pt; font-weight: bold; color: #4f46e5; }
.stat-lbl    { font-size: 8pt; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; }
.warning-box { background: #fef3c7; border: 1px solid #f59e0b; border-radius: 6px; padding: 12px 16px; margin: 30px auto; max-width: 500px; font-size: 8.5pt; color: #92400e; }

/* ── Page elements ── */
.page-break { page-break-before: always; }
h1 { font-size: 16pt; font-weight: bold; color: #1e293b; border-bottom: 2px solid #4f46e5; padding-bottom: 6px; margin-bottom: 16px; margin-top: 0; }
h2 { font-size: 11pt; font-weight: bold; color: #334155; margin: 16px 0 8px; }
h3 { font-size: 9.5pt; font-weight: bold; color: #475569; margin: 10px 0 6px; }
p  { margin-bottom: 8px; }

/* ── Section headings ── */
.section-label { font-size: 7pt; font-weight: bold; color: #4f46e5; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 4px; }

/* ── Tables ── */
table { width: 100%; border-collapse: collapse; margin-bottom: 14px; font-size: 7.5pt; }
th { background: #f1f5f9; color: #475569; font-weight: bold; text-align: left; padding: 5px 7px; border: 1px solid #e2e8f0; font-size: 7pt; text-transform: uppercase; letter-spacing: 0.5px; }
td { padding: 4px 7px; border: 1px solid #e2e8f0; vertical-align: top; }
tr:nth-child(even) td { background: #f8fafc; }
.pk  { background: #fef9c3 !important; }
.fk  { background: #eff6ff !important; }

/* ── Badges ── */
.badge { display: inline-block; padding: 1px 6px; border-radius: 10px; font-size: 6.5pt; font-weight: bold; }
.badge-get    { background: #dcfce7; color: #166534; }
.badge-post   { background: #dbeafe; color: #1e40af; }
.badge-put    { background: #fef3c7; color: #92400e; }
.badge-patch  { background: #ffedd5; color: #9a3412; }
.badge-delete { background: #fee2e2; color: #991b1b; }
.badge-pk  { background: #fef9c3; color: #713f12; }
.badge-mul { background: #dbeafe; color: #1e40af; }
.badge-uni { background: #f3e8ff; color: #6b21a8; }

/* ── Info boxes ── */
.info-box  { background: #f8fafc; border-left: 3px solid #4f46e5; padding: 8px 12px; margin: 8px 0; border-radius: 0 4px 4px 0; }
.warn-box  { background: #fef9c3; border-left: 3px solid #f59e0b; padding: 8px 12px; margin: 8px 0; }
.rule-box  { background: #fff1f2; border-left: 3px solid #f43f5e; padding: 8px 12px; margin: 8px 0; }
code { font-family: DejaVu Sans Mono, Courier New, monospace; font-size: 7.5pt; background: #f1f5f9; padding: 1px 4px; border-radius: 3px; }

/* ── Module cards ── */
.module-card { border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 12px; overflow: hidden; }
.module-header { background: #f8fafc; padding: 8px 12px; border-bottom: 1px solid #e2e8f0; }
.module-body   { padding: 8px 12px; }
.module-name   { font-weight: bold; color: #1e293b; font-size: 9.5pt; }
.module-ctrl   { font-family: DejaVu Sans Mono, Courier; font-size: 7pt; color: #94a3b8; }
.tag { display: inline-block; background: #ede9fe; color: #5b21b6; padding: 1px 6px; border-radius: 10px; font-size: 6.5pt; font-weight: bold; margin: 1px; }
.tag-amber { background: #fef3c7; color: #92400e; }
.tag-red   { background: #fee2e2; color: #991b1b; }

/* ── Two-column layout ── */
.col2 { display: table; width: 100%; }
.col2-left  { display: table-cell; width: 48%; vertical-align: top; padding-right: 10px; }
.col2-right { display: table-cell; width: 48%; vertical-align: top; padding-left: 10px; }

/* ── TOC ── */
.toc-entry { display: table; width: 100%; padding: 4px 0; border-bottom: 1px dotted #e2e8f0; font-size: 9pt; }
.toc-num   { display: table-cell; width: 28px; color: #4f46e5; font-weight: bold; }
.toc-title { display: table-cell; }
.toc-dots  { display: table-cell; }
</style>
</head>
<body>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- COVER PAGE                                                              --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="cover">
  <div class="cover-logo">Philippine Science High School — Caraga Region Campus</div>
  <div class="cover-title">CRCMIS</div>
  <div class="cover-sub">Technical Documentation</div>

  <div class="cover-stats">
    <div class="cover-stat">
      <div class="stat-num">{{ number_format($stats['routes']) }}</div>
      <div class="stat-lbl">Routes</div>
    </div>
    <div class="cover-stat">
      <div class="stat-num">{{ $stats['tables'] }}</div>
      <div class="stat-lbl">DB Tables</div>
    </div>
    <div class="cover-stat">
      <div class="stat-num">{{ $stats['permissions'] }}</div>
      <div class="stat-lbl">Permissions</div>
    </div>
    <div class="cover-stat">
      <div class="stat-num">{{ $stats['users'] }}</div>
      <div class="stat-lbl">Active Users</div>
    </div>
  </div>

  <div class="warning-box">
    ⚠ <strong>CONFIDENTIAL — RESTRICTED DISTRIBUTION</strong><br>
    This document contains architectural and infrastructure details of a government information system.
    Handle in accordance with data classification policies. Do not distribute externally.
  </div>

  <div class="cover-meta">
    Generated: {{ $generated_at }}<br>
    Laravel {{ app()->version() }} · PHP {{ PHP_VERSION }} · MySQL 8.0
  </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- TABLE OF CONTENTS                                                       --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="page-break">
  <h1>Table of Contents</h1>
  @foreach ([
    ['1', 'System Overview'],
    ['2', 'Module Reference  (' . count($permissions) . ' modules)'],
    ['3', 'Routes / API Reference  (' . number_format(count($routes)) . ' named routes)'],
    ['4', 'Database Schema  (' . count($schema) . ' tables)'],
    ['5', 'Permissions Reference  (' . $stats['permissions'] . ' permissions)'],
    ['6', 'Code Conventions'],
    ['7', 'Infrastructure & Deployment'],
    ['8', 'Changelog'],
  ] as [$num, $title])
  <div class="toc-entry">
    <div class="toc-num">{{ $num }}</div>
    <div class="toc-title">{{ $title }}</div>
  </div>
  @endforeach
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- 1. SYSTEM OVERVIEW                                                      --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="page-break">
  <div class="section-label">Section 1</div>
  <h1>System Overview</h1>

  <h2>Purpose</h2>
  <p>CRCMIS (Campus Resource and Campus Management Information System) is a comprehensive MIS for the Philippine Science High School – Caraga Region Campus (PSHS-CRC). It covers HR, payroll, faculty loading, performance management (IPCR/PMS), recruitment, library, service requests, SALN, document tracking, class records, WFH attendance, and more.</p>

  <h2>Technology Stack</h2>
  <table>
    <tr><th>Layer</th><th>Technology</th></tr>
    @foreach ([
      ['Backend',          'Laravel 12 · PHP 8.4 (FPM)'],
      ['Frontend',         'Vue 3 (script setup) · Inertia.js 2 · Vite 7'],
      ['Styling',          'Tailwind CSS 3 · Heroicons 2'],
      ['Database',         'MySQL 8.0 (AWS RDS Multi-AZ, KMS-encrypted)'],
      ['Cache / Queue',    'Redis 7.0 (AWS ElastiCache)'],
      ['Real-time',        'Soketi (self-hosted Pusher) · Laravel Echo · Pusher JS SDK'],
      ['Web Push',         'minishlink/web-push v9 · VAPID · FCM · crcmis-sw.js'],
      ['File Storage',     'AWS S3 (Block Public Access ON) — served via /media/ proxy'],
      ['PDF',              'mPDF 8 (tempDir = sys_get_temp_dir())'],
      ['Excel',            'PhpSpreadsheet · Maatwebsite Excel'],
      ['Container',        'Docker · AWS ECS Fargate (single container: nginx + PHP-FPM + cron + queue)'],
      ['CI/CD',            'GitHub Actions → ECR (immutable tags) → ECS rolling deploy'],
      ['CDN / WAF',        'Cloudflare (orange-cloud proxy, WAF active)'],
      ['Secrets',          'AWS SSM Parameter Store /crcmis/prod/* + Secrets Manager'],
    ] as [$layer, $tech])
    <tr><td><strong>{{ $layer }}</strong></td><td>{{ $tech }}</td></tr>
    @endforeach
  </table>

  <h2>Production Infrastructure</h2>
  <table>
    <tr><th>Resource</th><th>Detail</th></tr>
    @foreach ([
      ['App URL',         'https://mis.crc.pshs.edu.ph'],
      ['ECS Cluster',     'crcmis-prod (Fargate, 2 tasks, circuit breaker ON)'],
      ['ECR Repository',  '971422671747.dkr.ecr.ap-southeast-1.amazonaws.com/crcmis/app'],
      ['RDS',             'crcmis-db-encrypted.c5i2kaqa8hyl.ap-southeast-1.rds.amazonaws.com'],
      ['Redis',           'crcmis-redis.d8qigv.0001.apse1.cache.amazonaws.com:6379'],
      ['S3 Bucket',       'crcmis-mis-storage (ap-southeast-1, Block Public Access ON)'],
      ['ALB',             'crcmis-alb (HTTPS only, TLS 1.3, HTTP→HTTPS redirect)'],
      ['Health Check',    'GET /_status → 200 OK (used by ALB/ECS)'],
    ] as [$r, $d])
    <tr><td><strong>{{ $r }}</strong></td><td><code>{{ $d }}</code></td></tr>
    @endforeach
  </table>

  <h2>⚠ Critical Rules</h2>
  <div class="rule-box">
    <strong>NEVER use FormData / multipart/form-data for file uploads.</strong><br>
    Cloudflare WAF blocks all multipart requests with 403. Always send files as base64 data URI in a JSON body.
  </div>
  <div class="rule-box">
    <strong>NEVER use Storage::disk('public').</strong><br>
    S3 Block Public Access is ON. All files must use Storage::disk('s3') and be served through the /media/ proxy route.
  </div>
  <div class="rule-box">
    <strong>NEVER use storage_path() for mPDF.</strong><br>
    PHP open_basedir restriction blocks /var/www/storage/. Use sys_get_temp_dir() for mPDF's tempDir.
  </div>
  <div class="info-box">
    <strong>Run Artisan in dev:</strong><br>
    <code>docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan COMMAND"</code><br><br>
    <strong>Run Artisan in production (ECS exec):</strong><br>
    <code>TASK=$(aws ecs list-tasks --cluster crcmis-prod --query 'taskArns[0]' --output text)</code><br>
    <code>aws ecs execute-command --cluster crcmis-prod --task $TASK --container nginx --interactive --command "php /var/www/artisan COMMAND"</code>
  </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- 2. MODULE REFERENCE                                                     --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="page-break">
  <div class="section-label">Section 2</div>
  <h1>Module Reference</h1>

  @foreach ([
    ['HR — Leave Applications',   'hr.leave.*',                    'HR\\LeaveApplicationController',            'CSC Form No. 6 leave requests. 3-stage approval: Division Chief → HR Officer. Auto-syncs approved leaves to DTR. Supports all CSC leave types. Work week only (Mon–Fri).', ['hr.leave.view','hr.leave.apply','hr.leave.approve']],
    ['HR — DTR / Attendance',      'hr.dtr.*',                      'HR\\DtrRecordController',                    'Daily Time Record tracking with biometric sync. Handles travel flag and gate pass deduction. Monthly DTR generation for payroll input.', ['hr.dtr.view','hr.dtr.manage']],
    ['HR — WFH Attendance',        'hr.wfh.*',                      'WFHAttendanceController',                    'Work-From-Home attendance with photo-verified time-in/out. Photos captured via camera → base64 JSON → decoded → private S3. Served through /hr/wfh/photo/{fileId} proxy.', ['hr.wfh.view','hr.wfh.submit']],
    ['Payroll',                    'payroll.*',                     'Payroll\\PayrollRunController',              'Full payroll: salary from SSL schedule, deductions (GSIS, PhilHealth, Pag-IBIG, tax), per-run PDF payslips, cashier disbursement workflow with per-batch items.', ['payroll.view','payroll.manage','payroll.cashier']],
    ['IPCR / PMS',                 'ipcr.*|pms.*',                  'IPCRController, PMSController',              'Individual Performance Commitment and Review. Targets, accomplishments, ratings, Division Chief review, PMT approval cycle.', ['ipcr.view','ipcr.submit','ipcr.approve']],
    ['Faculty Loading',            'faculty-loading.*',             'FacultyLoading\\*',                          'AI-assisted schedule generation, overload computation, designation management. Records locked by SchoolYear.is_current. Overload pay linked to SstPosition.', ['faculty_loading.view','faculty_loading.manage']],
    ['Class Records',              'class-records.*',               'ClassRecord\\*',                             'Grade recording with running grade: floor((current × 2/3) + (previous × 1/3)) for Q2–Q4. CSV bulk import parsed client-side to avoid Cloudflare WAF. Locked for past school years.', ['class-records.view','class-records.manage']],
    ['IT Job Requests',            'jobrequests.*',                 'ITJobRequestController',                     '3-stage approval: DC → OCD → MIS. Priority queue, MIS assessment modal, PDF generation, CSM feedback. Technical Assistance on Events: 3-day advance filing rule.', ['it.view','it.manage']],
    ['Document Tracking',          'document-tracking.*',           'DocumentTrackingController',                 'Internal + external documents. External: Records logs + scan (→ Google Drive) → OCD reviews → routes to offices. Template-based routing chains (sequential/parallel/manual).', ['documents.view','documents.log','documents.route']],
    ['Recruitment',                'recruitment.*',                 'Recruitment\\*',                             'End-to-end: job postings, applications, shortlisting, interview scheduling, placement approval workflow.', ['recruitment.view','recruitment.manage']],
    ['SALN',                       'saln.*',                        'SALN\\*',                                    'Statement of Assets, Liabilities, and Net Worth. Annual submission per CSC requirements. PDF export.', ['saln.view','saln.submit']],
    ['PDS',                        'pds.*',                         'PDSController',                              'Personal Data Sheet (CSC Form 212 Rev. 2017) with Work Experience Sheet (WES) tab. Employee self-service with HR review.', ['hr.employees.view']],
    ['Requests (4 modules)',       'vehicle-requests.*|facility-requests.*|service-requests.*|messengerial.*', 'VehicleRequestController et al.', 'Campus service requests with multi-stage approval. Facility/Vehicle require 3-day advance filing. Messengerial has transmittal slip PDF; signature not placed in "Received By".', ['vehicles.view','facilities.view']],
    ['Chat',                       'chat.*',                        'ChatController',                             'Real-time messaging via Soketi (Pusher protocol). Falls back to HTTP polling when Cloudflare blocks WebSocket upgrades. Initials-based avatars.', []],
    ['Notifications',              'api/notifications.*',           'NotificationController',                     'In-app bell (database + Soketi broadcast) + Web Push via FCM (VAPID). Service worker: crcmis-sw.js. VAPID public key baked into Vite bundle at build time.', []],
  ] as [$name, $route, $ctrl, $desc, $perms])
  <div class="module-card">
    <div class="module-header">
      <span class="module-name">{{ $name }}</span>
      <span class="module-ctrl"> · {{ $ctrl }}</span>
    </div>
    <div class="module-body">
      <p style="margin-bottom:6px;">{{ $desc }}</p>
      <div>
        <span style="font-size:7pt; color:#94a3b8; margin-right:4px;">ROUTE:</span>
        <code>{{ $route }}</code>
      </div>
      @if(count($perms))
      <div style="margin-top:4px;">
        <span style="font-size:7pt; color:#94a3b8; margin-right:4px;">PERMISSIONS:</span>
        @foreach($perms as $p)<span class="tag tag-amber">{{ $p }}</span> @endforeach
      </div>
      @endif
    </div>
  </div>
  @endforeach
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- 3. ROUTES                                                               --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="page-break">
  <div class="section-label">Section 3</div>
  <h1>Routes / API Reference</h1>
  <p style="margin-bottom:10px;">Named routes only ({{ number_format(count($routes)) }} total). Sorted by URI. HEAD methods excluded.</p>

  <table>
    <tr>
      <th style="width:10%">Method</th>
      <th style="width:30%">URI</th>
      <th style="width:25%">Route Name</th>
      <th style="width:35%">Controller</th>
    </tr>
    @foreach($routes as $r)
    <tr>
      <td>
        @foreach(explode('|', $r['methods']) as $m)
          <span class="badge badge-{{ strtolower($m) }}">{{ $m }}</span>
        @endforeach
      </td>
      <td><code>{{ $r['uri'] }}</code></td>
      <td style="font-size:7pt; color:#64748b;">{{ $r['name'] }}</td>
      <td style="font-size:6.5pt; color:#94a3b8; font-family: DejaVu Sans Mono;">{{ $r['controller'] }}</td>
    </tr>
    @endforeach
  </table>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- 4. DATABASE SCHEMA                                                      --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="page-break">
  <div class="section-label">Section 4</div>
  <h1>Database Schema</h1>
  <p style="margin-bottom:10px;">{{ count($schema) }} tables. PK = Primary Key · MUL = Foreign Key Index · UNI = Unique.</p>

  @foreach($schema as $table)
  <h3 style="margin-top:14px; font-family: DejaVu Sans Mono; font-size:9pt;">
    {{ $table['name'] }}
    <span style="font-weight:normal; color:#94a3b8; font-size:7.5pt;">({{ count($table['columns']) }} cols)</span>
  </h3>
  <table>
    <tr>
      <th style="width:22%">Column</th>
      <th style="width:18%">Type</th>
      <th style="width:7%">Null</th>
      <th style="width:7%">Key</th>
      <th style="width:10%">Extra</th>
      <th style="width:36%">Foreign Key</th>
    </tr>
    @foreach($table['columns'] as $col)
    <tr class="{{ $col['key'] === 'PRI' ? 'pk' : ($col['fk'] ? 'fk' : '') }}">
      <td style="font-family: DejaVu Sans Mono; font-size:7.5pt; font-weight: {{ $col['key'] === 'PRI' ? 'bold' : 'normal' }};">{{ $col['name'] }}</td>
      <td style="font-family: DejaVu Sans Mono; font-size:7pt; color:#4f46e5;">{{ $col['type'] }}</td>
      <td style="font-size:7pt; color:#94a3b8;">{{ $col['nullable'] ? 'YES' : '' }}</td>
      <td>
        @if($col['key'])
        <span class="badge badge-{{ strtolower($col['key'] === 'MUL' ? 'mul' : ($col['key'] === 'PRI' ? 'pk' : 'uni')) }}">{{ $col['key'] }}</span>
        @endif
      </td>
      <td style="font-size:6.5pt; color:#64748b;">{{ $col['extra'] }}</td>
      <td style="font-size:6.5pt; color:#1d4ed8; font-family: DejaVu Sans Mono;">{{ $col['fk'] }}</td>
    </tr>
    @endforeach
  </table>
  @endforeach
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- 5. PERMISSIONS                                                          --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="page-break">
  <div class="section-label">Section 5</div>
  <h1>Permissions Reference</h1>
  <div class="info-box" style="margin-bottom:12px;">
    <strong>Usage:</strong> <code>$user->hasPermission('module.sub.action')</code><br>
    <strong>Middleware (ANY):</strong> <code>permission:a|b</code> &nbsp;·&nbsp;
    <strong>Middleware (ALL):</strong> <code>permission:a,b</code><br>
    <strong>Note:</strong> <code>Administrator</code> role bypasses all permission checks.
  </div>

  @foreach($permissions as $group => $perms)
  <h3 style="text-transform:capitalize; margin-top:10px;">{{ $group }} ({{ count($perms) }})</h3>
  <div style="margin-bottom:8px;">
    @foreach($perms as $p)
    <span class="tag tag-amber" style="margin:2px;">{{ $p }}</span>
    @endforeach
  </div>
  @endforeach
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- 6. CODE CONVENTIONS                                                     --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="page-break">
  <div class="section-label">Section 6</div>
  <h1>Code Conventions</h1>

  <h2>PHP / Laravel</h2>
  <ul style="margin-left:16px; margin-bottom:10px;">
    <li>Thin controllers — move business logic to Service classes in <code>app/Services/</code></li>
    <li>Always eager load to avoid N+1: <code>User::with(['role', 'division', 'office'])</code></li>
    <li>Permission middleware: <code>permission:a|b</code> (ANY) · <code>permission:a,b</code> (ALL)</li>
    <li>Soft delete = set <code>status = 'inactive'</code> (no Laravel SoftDeletes trait)</li>
    <li>After mutation: <code>return back()->with('success', 'Message.')</code></li>
    <li>After create: <code>return redirect()->route('resource.index')->with('success', '...')</code></li>
    <li>mPDF tempDir must use <code>sys_get_temp_dir()</code> — never <code>storage_path()</code></li>
    <li>File uploads: base64 decode → <code>Storage::disk('s3')->put()</code> — never multipart</li>
  </ul>

  <h2>Vue / Frontend</h2>
  <ul style="margin-left:16px; margin-bottom:10px;">
    <li>Always use <code>&lt;script setup&gt;</code> (Composition API) — no Options API</li>
    <li>Icons only from <code>@heroicons/vue/24/outline</code></li>
    <li>Forms: <code>useForm()</code> from <code>@inertiajs/vue3</code> or axios JSON</li>
    <li>Currency: <code>toLocaleString('en-PH', { minimumFractionDigits: 2 })</code></li>
    <li>Dates: <code>toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })</code></li>
    <li>Pagination: <code>PER_PAGE = 15</code>, local computed slice</li>
    <li>No TypeScript, no <code>.ts</code> files, no <code>@ts-check</code></li>
    <li>No Vuex / Pinia — use Inertia props + local <code>ref()</code></li>
  </ul>

  <h2>Database / Migrations</h2>
  <ul style="margin-left:16px; margin-bottom:10px;">
    <li>Filename: <code>YYYY_MM_DD_HHMMSS_description_snake_case.php</code></li>
    <li>Always write <code>down()</code> to reverse the migration</li>
    <li>Add columns with <code>-&gt;after('existing_column')</code></li>
    <li>Never modify an existing migration — add a new one</li>
    <li>Foreign keys: use <code>constrained()</code> with appropriate <code>onDelete</code></li>
  </ul>

  <h2>Git Workflow</h2>
  <ul style="margin-left:16px; margin-bottom:10px;">
    <li>Stage by specific file name — never <code>git add -A</code> or <code>git add .</code></li>
    <li>Commit messages: imperative mood, short summary</li>
    <li>Deploy: <code>git checkout main &amp;&amp; git merge junlou &amp;&amp; git push origin main</code></li>
    <li>Never force push to <code>main</code></li>
    <li>Never skip hooks (<code>--no-verify</code>)</li>
  </ul>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- 7. INFRASTRUCTURE                                                       --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="page-break">
  <div class="section-label">Section 7</div>
  <h1>Infrastructure &amp; Deployment</h1>

  <h2>CI/CD Pipeline</h2>
  <ol style="margin-left:16px; margin-bottom:10px;">
    <li>Developer pushes to <code>main</code> branch</li>
    <li>GitHub Actions builds Docker image via <code>docker buildx</code> with VITE_* as <code>--build-arg</code></li>
    <li>Image pushed to ECR with immutable tag (commit SHA)</li>
    <li>ECS task definition updated with new image ARN</li>
    <li>ECS rolling deployment: new tasks start, health checks pass, old tasks drain</li>
    <li>On container start: <code>docker-entrypoint.sh</code> fetches Google credentials from Secrets Manager, runs pending migrations, then starts supervisord (nginx + php-fpm + cron + queue worker)</li>
  </ol>

  <h2>Secrets Management</h2>
  <table>
    <tr><th>Secret</th><th>Location</th></tr>
    <tr><td>Database, Mail, Redis, VAPID, S3 keys</td><td><code>SSM Parameter Store /crcmis/prod/*</code></td></tr>
    <tr><td>Google Drive service account JSON</td><td><code>Secrets Manager: crcmis/google-drive-credentials</code></td></tr>
    <tr><td>VAPID public key (frontend)</td><td>Baked into Vite bundle at build time as <code>VITE_VAPID_PUBLIC_KEY</code></td></tr>
  </table>

  <h2>PHP Security Configuration (Production)</h2>
  <table>
    <tr><th>Setting</th><th>Value</th><th>Reason</th></tr>
    <tr><td><code>open_basedir</code></td><td><code>/var/www:/tmp:/usr/local/etc/php</code></td><td>Restrict file access</td></tr>
    <tr><td><code>max_execution_time</code></td><td><code>120</code></td><td>Prevent runaway scripts</td></tr>
    <tr><td><code>allow_url_fopen</code></td><td><code>Off</code></td><td>Block remote file inclusion</td></tr>
    <tr><td><code>disable_functions</code></td><td><code>system, shell_exec, passthru, proc_open, popen, pcntl_exec</code></td><td>Block shell access</td></tr>
    <tr><td><code>exec()</code></td><td>Allowed</td><td>Required for mysqldump backup cron</td></tr>
    <tr><td><code>session.cookie_httponly</code></td><td><code>1</code></td><td>Prevent JS session access</td></tr>
    <tr><td><code>session.cookie_secure</code></td><td><code>1</code></td><td>HTTPS only cookies</td></tr>
  </table>

  <h2>nginx Rate Limiting</h2>
  <table>
    <tr><th>Zone</th><th>Rate</th><th>Applied to</th></tr>
    <tr><td>login</td><td>10 req/min</td><td><code>POST /login</code></td></tr>
    <tr><td>api</td><td>60 req/min (burst 20)</td><td>All <code>*.php</code> requests</td></tr>
  </table>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- 8. CHANGELOG                                                            --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="page-break">
  <div class="section-label">Section 8</div>
  <h1>Changelog</h1>

  @foreach ([
    ['2.5.0', 'May 2026', [
      'Technical Documentation PDF generator (docs:generate artisan command)',
      'Comprehensive Profile module — base64 photo upload, employment details display',
      'Document Tracking System — internal + external, Google Drive scan storage, routing templates with office+user assignment',
      'Web Push notifications (FCM/VAPID) + in-app bell for all modules',
      'IT Job Request priority queue and MIS assessment modal',
    ]],
    ['2.4.0', 'May 2026', [
      'AWS infrastructure security hardening (A− security rating)',
      'CloudTrail audit logging, ECR scan-on-push enabled',
      'S3 IAM policy scoped to crcmis-mis-storage only',
      'nginx rate limiting (login: 10/min, API: 60/min)',
      'PHP security hardening (open_basedir, disable_functions)',
      'Google Drive credentials moved to AWS Secrets Manager',
    ]],
    ['2.3.0', 'May 2026', [
      'WFH photos migrated from Google Drive to private S3 with proxy route',
      'Payroll Cashier disbursement workflow with per-batch items',
      'Messengerial: removed division chief from "Received By" section',
      'Gate pass: OCD division bypasses division chief approval',
      'MIS Dashboard personnel workload normalized to 3 MIS staff',
    ]],
    ['2.2.0', 'April 2026', [
      'Class Record module with running grade computation (floor not round)',
      'CSM Feedback module (polymorphic respondable pattern)',
      'PDS Work Experience Sheet (WES) tab',
      'DTR travel flag and gate pass deduction integration',
    ]],
    ['2.1.0', 'March 2026', [
      'Production deployment to AWS ECS Fargate',
      'Cloudflare WAF + ALB TLS 1.3',
      'GitHub Actions CI/CD pipeline with ECR + ECS',
      'S3 private bucket with /media/ proxy route',
    ]],
  ] as [$ver, $date, $items])
  <h2>v{{ $ver }} <span style="font-weight:normal; color:#94a3b8; font-size:9pt;">— {{ $date }}</span></h2>
  <ul style="margin-left:16px; margin-bottom:12px;">
    @foreach($items as $item)
    <li>{{ $item }}</li>
    @endforeach
  </ul>
  @endforeach
</div>

</body>
</html>
