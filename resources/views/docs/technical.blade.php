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
  <div class="cover-title">Atlas</div>
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
    Platform Version 1.0.0<br>
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
    ['2', 'Module Reference  (59 modules in 6 groups)'],
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
  <p>Atlas (formerly CRCMIS / BugSayMis) is the unified digital campus management platform for the Philippine Science High School – Caraga Region Campus (PSHS-CRC). It covers HR, payroll, faculty loading, performance management (IPCR/PMS), recruitment, registrar and student services, library, service requests, procurement and supply, SALN, document tracking, class records, WFH attendance, real-time chat, a student portal with the AtlasGo mobile app, and the Atlas platform tools (Sentinel fleet management, WatchTower telemetry, Module Monitor).</p>

  <h2>Technology Stack</h2>
  <table>
    <tr><th>Layer</th><th>Technology</th></tr>
    @foreach ([
      ['Backend',          'Laravel 12 · PHP 8.4 (FPM)'],
      ['Frontend',         'Vue 3 (script setup) · Inertia.js 2 · Vite 7'],
      ['Styling',          'Tailwind CSS 3 · Heroicons 2'],
      ['Database',         'MySQL 8.0 (AWS RDS, Single-AZ, KMS-encrypted, deletion protection ON)'],
      ['Cache / Queue',    'Redis 7.0 (AWS ElastiCache replication group)'],
      ['Real-time',        'Soketi (self-hosted Pusher) · Laravel Echo · Pusher JS SDK'],
      ['Web Push',         'minishlink/web-push v9 · VAPID · FCM · atlas-sw.js'],
      ['File Storage',     'AWS S3 (Block Public Access ON) — served via /media/ proxy'],
      ['PDF',              'mPDF 8 (tempDir = sys_get_temp_dir())'],
      ['Excel',            'PhpSpreadsheet · Maatwebsite Excel'],
      ['Container',        'Docker · AWS ECS Fargate — web task (nginx edge + app + soketi + ADOT sidecars) + separate worker service (cron + queue + Pulse)'],
      ['Observability',    'AWS X-Ray + OpenTelemetry (ADOT collector sidecar, OTLP :4318) · Laravel Pulse (worker service)'],
      ['CI/CD',            'GitHub Actions → ECR (immutable tags) → ECS native blue/green deploy'],
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
      ['ECS Cluster',     'crcmis-prod (Fargate) — crcmis-prod-service (web, blue/green) + crcmis-prod-worker (cron/queue/Pulse)'],
      ['ECR Repositories', 'crcmis/app · crcmis/nginx — 971422671747.dkr.ecr.ap-southeast-1.amazonaws.com'],
      ['RDS',             'crcmis-db-encrypted.c5i2kaqa8hyl.ap-southeast-1.rds.amazonaws.com (Single-AZ)'],
      ['Redis',           'crcmis-redis-rg.d8qigv.ng.0001.apse1.cache.amazonaws.com:6379 (replication group primary)'],
      ['S3 Bucket',       'crcmis-mis-storage (ap-southeast-1, Block Public Access ON)'],
      ['ALB',             'crcmis-alb (HTTPS only, TLS 1.3) — re-encrypts to target 443, where the nginx edge sidecar terminates with a Cloudflare Origin Certificate'],
      ['Health Check',    'GET /_status → 200 OK (used by ALB/ECS)'],
      ['Auto-scaling',    'Web service min 2 / max 4 tasks — target tracking on CPU 60% and memory 75%'],
      ['Tracing',         'AWS X-Ray via ADOT collector sidecar — OpenTelemetry spans for HTTP, queries, queue jobs, Redis'],
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
  <div class="rule-box">
    <strong>Migrations run pre-deploy, not on container boot.</strong><br>
    A one-off ECS Fargate task runs migrations before the service update; the deploy aborts if it fails. Because blue (old) and green (new) code run side-by-side against the same RDS schema during the cutover, destructive changes (drop/rename column, type change, NOT NULL on existing) must be split across two deploys — expand (additive, nullable) then contract (drop old shape) once all code uses the new shape.
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
    'Human Resources & Payroll' => [
      ['Leave Applications',        'hr.leave.*',                    'HR\\LeaveApplicationController',            'CSC Form No. 6 leave requests. 3-stage approval: Division Chief → HR Officer. Auto-syncs approved leaves to DTR. Supports all CSC leave types. Work week only (Mon–Fri).', ['hr.leave.view','hr.leave.apply','hr.leave.approve']],
      ['Leave Credits',             'leave-credits.*',               'HR\\LeaveCreditAdminController',             'VL/SL monthly accrual engine (LeaveCreditService) with per-employee ledger, HR adjustments, and initialization. Teaching staff excluded from accrual except SSD/CID special chiefs.', []],
      ['DTR / Attendance',          'hr.dtr.*|dtr.*',                'HR\\DtrRecordController',                    'Daily Time Record with biometric sync, travel flag, and gate pass deduction. COS employees can self-generate advance cut-off entries; HR penned-entry unlock; admin edits sync WFH rows. Monthly DTR generation for payroll input.', ['hr.dtr.view','hr.dtr.manage']],
      ['WFH Attendance',            'hr.wfh.*',                      'WFHAttendanceController',                    'Work-From-Home attendance with photo-verified time-in/out. Photos captured via camera → base64 JSON → decoded → private S3. Served through /hr/wfh/photo/{fileId} proxy.', ['wfh.time-in','wfh.time-out','wfh.monitor']],
      ['Gate Pass',                 'gatepass.*',                    'HumanResource\\GatePassController',          '3-stage gate pass flow with printable form. DTR deduction is derived live from approved passes, so it survives DTR regeneration. OCD division bypasses Division Chief approval.', []],
      ['Employee Digital ID',       'employee-id.*',                 'EmployeeIdController',                       'Wallet-style digital employee ID with opaque-token QR code and public live-status verification page. Revocable per employee; includes once-per-login digital signature setup prompt.', []],
      ['HR Dashboard',              'hr.dashboard',                  'HR\\HRDashboardController',                  'Cross-module HR analytics spanning HR, Recruitment, PMS, L&D, SALN, and Rewards, with today\'s leave/gate-pass widget and deduplicated employee counts.', ['hr.dashboard.view']],
      ['Payroll',                   'payroll.*',                     'Payroll\\PayrollRunController',              'Full payroll: salary from SSL schedule, deductions (GSIS, PhilHealth, Pag-IBIG, tax), per-run PDF payslips, cashier disbursement workflow with per-batch items and combined PDF.', ['payroll.view','payroll.manage','payroll.process']],
      ['PDS',                       'pds.*',                         'PDSController',                              'Personal Data Sheet (CSC Form 212) with Work Experience Sheet (WES) tab. Excel export embeds passport photo, digital signature, and export date at exact cell coordinates. Hardened trainings CSV import (BOM/Win-1252, per-row skip reporting).', ['hr.employees.view']],
      ['SALN',                      'saln.*',                        'SALN\\*',                                    'Statement of Assets, Liabilities, and Net Worth. Annual submission per CSC requirements with review workflow and PDF export.', ['saln.file','saln.review']],
      ['Recruitment',               'recruitment.*',                 'Recruitment\\*',                             'End-to-end: job postings (multiple plantilla item numbers per job item), public /jobs portal (base64 uploads → S3-staged, queued Google Drive transfer), shortlisting, interviews, placements, auto-generated downloadable art cards.', ['recruitment.publish','recruitment.evaluate','recruitment.approve']],
      ['Learning & Development',    'tna.*|idp.*|programs.*|sessions.*', 'LnD\\*',                                 'Training Needs Assessment, learning programs and sessions, participant management, training evaluations, and Individual Development Plans.', ['lnd.create','lnd.approve','lnd.evaluate']],
      ['Rewards & Recognition',     'rewards.*|nominations.*',       'Rewards\\*',                                 'Nomination → evaluation → approval workflow with reward types and recognition reports.', ['rewards.nominate','rewards.evaluate','rewards.approve']],
      ['Travel',                    'travel.*',                      'TravelController',                           'Travel authority requests with division/OCD approval stages, FAD review, and finance processing.', ['travel.create','travel.approve.division','travel.approve.ocd']],
    ],
    'Performance Management' => [
      ['IPCR / PMS',                'employee-ipcr.*|division-chief-employee-ipcr.*|pmt-ipcr.*|ipcr-rating-periods.*', 'EmployeeIPCRController, DivisionChiefIPCRController, PMTIPCRController, HRIPCRController, PMSController', 'CSC SPMS-aligned Individual Performance Commitment and Review — fiscal-year scoping, semestral rating periods, immutable submitted forms. Staff chain: employee → Division Chief → PMT → Director (variable signatory). Faculty chain: teacher → AUH → ACIDAA → CID Chief (ministerial), resolved via Data Management office links.', ['ipcr.create','ipcr.approve','ipcr.admin']],
      ['Committees & Task Board',   'pm-committees.*|committee-tasks.*', 'CommitteeController, CommitteeTaskController, CommitteePerformanceController', 'Shared committees table (Data Management + Faculty Loading + PMS) with Simple/Main structures, sync-to-term, a monday-style task board on both PM and FL pages, and per-rating-period member ratings by the chair.', []],
    ],
    'Curriculum & Instruction' => [
      ['Faculty Loading',           'faculty-loading.*',             'FacultyLoading\\*',                          'Deterministic conflict-free schedule generation (replaced the genetic algorithm), drag-and-drop rescheduling with live conflict detection, reserved elective windows, click-to-create calendar with section activities, My Faculty Schedule page, school-year-scoped subjects/classrooms/units, designation auto-sync (AUH-*, RES-G*), overload computation.', ['faculty_loading.manage','faculty_loading.approve']],
      ['Class Records',             'class-records.*',               'ClassRecord\\*',                             'Grade recording with running grade: floor((current × 2/3) + (previous × 1/3)) for Q2–Q4. CSV bulk import parsed client-side to avoid Cloudflare WAF. Locked for past school years. Final annual grades, A3 PDF export, teacher notification on admin check.', ['class-records.view','class-records.manage']],
      ['Live Quiz',                 'quiz.*',                        'Quiz\\QuizController, QuizSessionController, QuizPlayController', 'Kahoot-style live quizzes: hosted sessions with join codes, reveal-gated answers, streaks and 2× multiplier, get-ready intros, sounds, and post-session reports.', ['quiz.manage']],
      ['Science Lab Management',    'science-lab.*',                 'ScienceLab\\*',                              'CIM 4.4 — laboratories, equipment and reagent requests with endorsement/approval, reservations, calibration schedules, consumables, maintenance, waste disposal, safety, and reports.', ['lab.manage','lab.request','lab.calibration.approve']],
      ['Computer Lab Management',   'computer-labs.*',               'ComputerLabController',                      'Computer laboratory inventory and reservation management.', []],
      ['Competitions & Winnings',   'cid.competitions.*',            'CID\\CompetitionController',                 'Employee and student competition entries with coach/co-coach and per-person awards. Optional auto-filed Graphic Design IT Job Request on creation.', ['cid.competitions.view','cid.competitions.manage']],
      ['Activity Management',       'ams.*',                         'AMS\\*',                                     'Activity proposals (in-house vs training flows), co-proponents, monitoring dashboard, certificates generation, and post-activity evaluations.', ['activities.manage','activities.monitor']],
    ],
    'Registrar & Student Services' => [
      ['Student Information System','registrar.*|students.*',        'Registrar\\*, StudentController',            'Enrollment periods and applications (enroll-then-assign-section), bulk section assignment by grade level, promotions, report cards, transcripts, retention policies, analytics. /students admin datatable with inline edit, canvas photo crop, and CR-80 ID card printing.', ['students.manage','students.analytics.view']],
      ['Student Gate Attendance',   'students.attendance.*',         'StudentAttendance\\*',                       'Gate scan attendance via pisaysystemID barcode with SMS gate notifications to parents.', ['students.attendance.scan','students.attendance.view']],
      ['Student Clearance',         'clearance.*',                   'StudentClearance\\*',                        'Year-end clearance workflow with adviser review, advisory blockers, admin oversight, and PDF output.', ['students.clearance.admin','students.clearance.adviser-review']],
      ['Guidance & EGCU',           'guidance.*',                    'GuidanceConsultationController, Guidance\\CumulativeRecordController', 'Guidance consultations, session reports, kiosk sign-in, referrals, and the EGCU Cumulative Record.', ['guidance.manage','guidance.cumulative.manage']],
      ['Health Services',           'health.*|consultations.*',      'HealthController, ClinicKioskController, PhysicianScheduleController', 'Clinic consultation records, kiosk, physician schedules, and health statistics.', ['health.view','health.manage']],
      ['Library',                   'library.*',                     'LibraryCollectionsController, LibraryBorrowingsController, LibraryAttendanceController', 'Collections and categories, borrowing workflow, attendance kiosk.', ['library.manage']],
      ['Residence Hall',            'rh.*',                          'ResidenceHall\\*',                           'SSM 5.1 + 5.2 — applications (including student-portal filing), visual floor map with bed assignment grid, appliances, fees, housekeeping, incidents, dormer leave passes, waivers.', []],
      ['Student Discipline',        'discipline.*',                  'Discipline\\*',                              'SDO case management — Anecdotal Reports with PIN signing, offense catalogue, confiscated items, interventions, case PDFs.', ['discipline.file','discipline.manage']],
      ['Lost & Found',              'lostfound.*',                   'LostFoundController',                        'GSU custody trail for found items with honesty points (10 per turnover, awarded on GSU receive). Three surfaces including a mobile API.', ['lostfound.manage']],
      ['Student Portal & AtlasGo',  'student-portal.*|api/mobile/*', 'StudentPortal\\*, Api\\*',                   'Student portal with Firebase auth + Google sign-in and PISAY ID linking: grades, schedule, clearance, RH applications, and more. AtlasGo mobile app (Android direct APK download live; iOS submitted to App Store review) rides on the same services via the mobile API.', []],
    ],
    'Administration, Requests & Documents' => [
      ['IT Job Requests',           'jobrequests.*|it-job-requests.*', 'ITJobRequestController',                   '3-stage approval: DC → OCD → MIS. Priority queue, MIS assessment modal, PDF generation, CSM feedback. Technical Assistance on Events: 3-day advance filing rule.', ['it.requests.create','it.requests.manage']],
      ['ICT Preventive Maintenance','ict-pms.*',                     'ICTPMSHistoryController',                    'PMS programs per ICT equipment with agent-check history logging and PIN-signed OCD approval through the shared Approval Inbox.', ['it.equipment.manage']],
      ['Requests (4 modules)',      'vehicle-requests.*|facility-requests.*|service-requests.*|messengerial.*', 'VehicleRequestController et al.', 'Campus service requests with multi-stage approval (vehicles include a FAD step). Facility/Vehicle require 3-day advance filing. QR verification blocks on all General Services print forms.', ['vehicles.create','facilities.create','messengerial.create']],
      ['General Services / Work Requests', 'work-requests.*',        'GeneralServicesDashboardController',         'GSU work orders with categories, assignment, completion flow, and dashboard analytics.', ['work-orders.manage']],
      ['Document Tracking',         'document-tracking.*',           'DocumentTrackingController',                 'Internal + external documents. External: Records logs + scan (→ Google Drive) → OCD reviews → routes to offices. Template-based routing chains (sequential/parallel/manual).', ['documents.create','documents.approve']],
      ['Official Issuances',        'issuances.*',                   'IssuanceController',                         'Control-numbered issuances with Tiptap editor, QR stamping, PIN signing, queue-based PDF + email, full-text content search (smalot with AWS Textract fallback), and cryptographic tamper check on the public verify page.', ['issuances.view','issuances.manage']],
      ['Knowledge Management',      'km.*',                          'KnowledgeManagementController',              'OED issuance repository with immutable category codes and the Textract full-text search pipeline.', ['km.view','km.manage']],
      ['Announcements',             'announcements.*',               'Administration\\AnnouncementController',     'Campus announcements to all or targeted audiences — poster upload to S3, queued bell-notification fan-out, dashboard card.', ['announcements.manage']],
      ['Certificate of Appearance', 'coa.*',                         'Administration\\CoaController',              'Per-visitor QR-verified, PIN-signed Certificate of Appearance PDFs emailed to external visitors.', ['coa.manage']],
      ['Approval Inbox',            'approval-inbox.*',              'ApprovalInboxController',                    'Unified PIN-signed approval queue shared across modules (ICT PMS, vehicle requests, and other signable types).', []],
      ['PPMP',                      'ppmp.*',                        'PPMP\\*',                                    'Project Procurement Management Plan — unit PPMPs roll up to division consolidation, then Property Officer → Budget Officer → Head of Agency review, APP consolidation with APP-CSE catalogue compliance and Excel export.', ['ppmp.create','ppmp.consolidate','ppmp.approve']],
      ['Procurement',               'procurement.*',                 'Procurement\\*',                             'PR → ORS → DV workflow with accountant, bookkeeper, and cashier stages, OCD payment signing, and delivery tracking.', ['procurement.create','procurement.approve']],
      ['Supply & Property',         'supply.*|property.*',           'Supply\\*, Property\\*',                     'Supply: IAR, RIS, stock cards, item catalogue. Property: ICS/PAR issuance, transfers, disposal (BSR), and RPCI reports.', ['supply.manage','property.manage']],
      ['Executive Dashboard',       'executive.dashboard',           'ExecutiveDashboardController',               'Cross-system analytics for OCD and Division Chiefs — 8 cached sections with attention flags, unit scorecards, and per-lens scoping.', ['executive.dashboard.view']],
      ['Org Structure & Data Mgmt', 'org.*|units.*|heads.*',         'DataManagement\\*, OfficeController',        'Divisions, offices, and academic units with head assignments, org exports, and Sync to Faculty Loading.', ['org.units.create','org.heads.manage']],
      ['Users, Roles & Permissions','users.*|roles.*',               'UserController, RolesController',            'RBAC administration — users, many-to-many roles, permission grants. Administrator role bypasses all permission checks.', ['users.manage','roles.assign']],
      ['Error Reports',             'error-reports.*',               'ErrorReportController',                      'In-app error reporting with ERR-YYYY-NNNN reference numbers, S3 screenshots, and MIS triage workflow.', []],
      ['CSM Feedback',              'csm.*',                         'CSMFeedbackController, CsmResponseController', 'ARTA Client Satisfaction Measurement — polymorphic csm_responses attached to any respondable transaction, with dashboard and export.', []],
      ['GAD Dashboard',             'gad-data',                      'GadDataController',                          'Public gender-and-development statistics page with 1-hour cache.', []],
    ],
    'Platform & Atlas Tools' => [
      ['Chat',                      'chat.*',                        'ChatController',                             'Real-time messaging via Soketi (Pusher protocol) with groups, status ticks, typing indicators, image lightbox, drag-and-drop, and base64 attachments (10 MB cap, MIME whitelist). Falls back to HTTP polling when Cloudflare blocks WebSocket upgrades.', ['chat.access']],
      ['Notifications',             'api/notifications.*',           'NotificationController',                     'In-app bell (database + Soketi broadcast) + Web Push via FCM (VAPID). Service worker: atlas-sw.js. VAPID public key baked into Vite bundle at build time.', []],
      ['Personal Dashboard',        'dashboard',                     'DashboardController',                        'Personalized landing page — the user\'s own requests, schedule, announcements, and module shortcuts.', []],
      ['Atlas Sentinel',            'atlas-sentinel.*|ict-equipments.*', 'ICTEquipmentController, AtlasSentinelRemoteHelpController, AtlasSentinelBackupController', 'ICT fleet management (RMM) — equipment inventory, health dashboard with per-device Agent Specs (hardware/software snapshots, Wi-Fi SSID, network presence), health checks and alerts with IT Job Request escalation, admin-triggered remediation and self-healing rules, agent self-update via presigned S3 downloads, attended remote-help queue (AnyDesk sessions), scheduled document backups.', ['it.equipment.view','it.equipment.manage','atlas.sentinel.remote-help.manage']],
      ['Atlas WatchTower',          'atlas.watchtower.*',            'Atlas\\AtlasWatchTowerController',           'Live application telemetry dashboard — requests, slow queries, queue jobs, exceptions, active users. Reads AWS CloudWatch metrics and X-Ray traces via the app\'s ECS Task Role. Built after the LaraOwl incident for real-time admin visibility into infra health.', ['atlas.watchtower.view','atlas.watchtower.manage']],
      ['Atlas Module Monitor',      'atlas.modules.*',               'Atlas\\AtlasModuleController',               'Per-module health, maturity scoring, radar chart, and live usage metrics across the whole platform.', ['atlas.modules.view']],
      ['Profile',                   'profile.*',                     'ProfileController',                          'Self-service profile — name, specialization, base64 photo to S3 (profile_pictures/), digital signature management. Email and password are not user-editable (HR-managed / Google OAuth).', []],
    ],
  ] as $groupName => $cards)
  <h2 style="margin-top:18px; border-bottom:1px solid #e2e8f0; padding-bottom:4px;">{{ $groupName }}</h2>
  @foreach ($cards as [$name, $route, $ctrl, $desc, $perms])
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
    <li>Never <code>new DateTime()</code> with Eloquent date-cast attributes — use <code>Carbon::parse($value)->format('Y-m-d')</code> (PHP 8 type coercion silently produces 0 values)</li>
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
    <li>GitHub Actions builds <code>crcmis/app</code> (nginx + PHP-FPM app image) and <code>crcmis/nginx</code> (edge/TLS sidecar) via <code>docker buildx</code> with VITE_* as <code>--build-arg</code></li>
    <li>Images pushed to ECR with immutable tags (commit SHA)</li>
    <li>Pre-deploy migration runs as a one-off ECS Fargate task (<code>docker-entrypoint.sh migrate</code>) — the deploy aborts if this task fails</li>
    <li>ECS task definition updated with the new image ARNs; <code>crcmis-prod-service</code> (web) rolls out via native ECS blue/green deployment</li>
    <li><code>crcmis-prod-worker</code> (cron + queue + Pulse) is rolled separately, after the web service, to avoid double-firing scheduled/queued jobs during the blue/green overlap window</li>
    <li>Pipeline polls <code>aws ecs list-service-deployments</code> / <code>describe-service-deployments</code> until a terminal <code>SUCCESSFUL</code> or <code>ROLLBACK_*</code> status is reached</li>
    <li>On container start: <code>docker-entrypoint.sh</code> fetches Google credentials from Secrets Manager, then execs supervisord — web task runs nginx + php-fpm only; worker task runs cron + queue-worker + <code>pulse:work</code> only (migrations are no longer run on boot)</li>
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
  <div class="info-box">
    Rate-limit zones are keyed on the <strong>real client IP</strong> (restored from Cloudflare via <code>real_ip</code> directives) — not the ALB address, which would have throttled all users as one client. Fixed July 2026.
  </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- 8. CHANGELOG                                                            --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="page-break">
  <div class="section-label">Section 8</div>
  <h1>Changelog</h1>

  <div class="info-box" style="margin-bottom:12px;">
    The platform version is <strong>1.0.0</strong> (Initial Production Release, May 2, 2026 — see <code>app_versions</code>).
    Post-release changes are listed below as dated updates rather than version bumps.
  </div>

  @foreach ([
    ['July 2026 Update', 'July 2026', [
      'Observability: AWS X-Ray distributed tracing via OpenTelemetry (keepsuit/laravel-opentelemetry) and an ADOT collector sidecar (OTLP spans for HTTP, queries, queue jobs, Redis); Laravel Pulse on the worker service; Atlas WatchTower live telemetry dashboard (CloudWatch + X-Ray via ECS Task Role), built after the LaraOwl incident',
      'Production scaling: nginx rate limits re-keyed to the real client IP (were keyed on the ALB address), PHP-FPM worker pool tuning, OPcache tuning, Soketi Redis adapter, ECS auto-scaling min 2 / max 4, ALB idle timeout 120s',
      'UI/UX harmonization: 346 pages migrated to the shared App* component library and semantic color tokens',
      'IPCR: CSC SPMS overhaul — fiscal-year scoping, semestral rating periods, immutable submissions; faculty rating chain (teacher → AUH → ACIDAA → CID Chief) with variable Director signatory',
      'Executive Dashboard for OCD and Division Chiefs; monday-style Committee Task Board with per-rating-period member ratings',
      'New modules: Live Quiz, Student Clearance, Personal Dashboard, Science Laboratory Management (CIM 4.4), Activity Management, Lost & Found, Competitions & Winnings, Announcements, Certificate of Appearance, Employee Digital ID',
      'Atlas Sentinel: attended remote help (AnyDesk sessions), agent 1.1.x scale hardening with self-update via presigned S3 downloads, scheduled document backups',
      'AtlasGo mobile: student-portal API with Google sign-in; Android direct APK download live; iOS build submitted to App Store review',
      'Chat: 10 MB base64 attachments with MIME whitelist, typing indicators, image lightbox, drag-and-drop',
      'Faculty Loading: click-to-create schedule calendar, section activities, My Faculty Schedule page, non-teaching blocks',
    ]],
    ['June 2026 Update', 'June 2026', [
      'Atlas rebrand — CRCMIS/BugSayMis renamed to Atlas; login page and admin layout redesign',
      'ECS native blue/green deployment replaces rolling deploy — migrations now run as a pre-deploy one-off task (deploy aborts on failure); crcmis-prod-worker service (cron + queue) split out of the web task so scheduled/queued jobs cannot double-fire during the blue/green overlap',
      'Web task now runs 4 containers per Fargate task: nginx edge (TLS termination via Cloudflare Origin Cert — ALB re-encrypts to target 443, previously plain HTTP to 80), app (nginx + PHP-FPM), soketi, and an ADOT collector sidecar',
      'Atlas Sentinel RMM platform (Phases 0–3): agent enrollment (bulk tokens + MAC fallback), hardware/software snapshots, health checks and alerts with IT Job Request escalation, admin-triggered remediation, self-healing rules',
      'Faculty Loading: deterministic conflict-free scheduling engine replaces the genetic algorithm; drag-and-drop rescheduling with live conflict detection; reserved elective windows; school-year-scoped subjects, classrooms, and academic units; designation auto-sync',
      'New modules: Residence Hall (SSM 5.1 + 5.2), Student Discipline (SDO), Computer Laboratory Management, Knowledge Management (OED issuances), Error Reporting, Atlas Module Monitor, HR Dashboard, Online Time Punches (facial recognition)',
      'PPMP: full 3-level workflow — unit PPMPs → division consolidation → Property/Budget/Head-of-Agency review, APP-CSE catalogue compliance with Excel export',
      'Issuances + KM: full-text content search (smalot with AWS Textract fallback); cryptographic tamper check on the public verify page',
      'Students: CR-80 ID card printing, canvas photo crop, bulk section assignment; PSGC cascade address picker (42,046 barangays)',
      'DTR: COS advance entry at payroll cut-off, HR penned-entry unlock, WFH attendance sync on admin edits',
    ]],
    ['May 2026 Update', 'May 2026', [
      'AWS infrastructure security hardening — CloudTrail audit logging, ECR scan-on-push, S3 IAM policy scoped to crcmis-mis-storage, nginx rate limiting, PHP hardening (open_basedir, disable_functions), Google Drive credentials moved to AWS Secrets Manager',
      'Document Tracking System — internal + external documents, Google Drive scan storage, routing templates with office+user assignment',
      'Web Push notifications (FCM/VAPID) + in-app bell for all modules',
      'Comprehensive Profile module — base64 photo upload, employment details display',
      'WFH photos migrated from Google Drive to private S3 with proxy route',
      'Payroll Cashier disbursement workflow with per-batch items',
      'IT Job Request priority queue and MIS assessment modal',
      'Technical Documentation PDF generator (docs:generate artisan command)',
    ]],
    ['v1.0.0 — Initial Production Release', 'May 2, 2026', [
      'Unified campus platform launched to production: HR & personnel (201 files, PDS, leave, leave credits, DTR, WFH, schedules, SALN), performance management (IPCR 5-stage workflow, PMS, IDP), recruitment & rewards, learning & development, faculty loading & class schedules, student gate attendance, library, guidance & health, requests & general services (ITJR, vehicle, facility, service, messengerial), procurement & PPMP, document tracking, activity management, student & parent records, RBAC, real-time chat, audit logs, org structure, salary schedules, dashboards',
      'Deployed on AWS ECS Fargate behind Cloudflare WAF + ALB TLS 1.3, GitHub Actions CI/CD with ECR, private S3 with /media/ proxy route',
      'Built on Laravel 12 · Vue 3 (Composition API) · Inertia.js 2 · MySQL 8.0',
    ]],
  ] as [$ver, $date, $items])
  <h2>{{ $ver }} <span style="font-weight:normal; color:#94a3b8; font-size:9pt;">— {{ $date }}</span></h2>
  <ul style="margin-left:16px; margin-bottom:12px;">
    @foreach($items as $item)
    <li>{{ $item }}</li>
    @endforeach
  </ul>
  @endforeach
</div>

</body>
</html>
