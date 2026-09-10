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
.cover-sub   { font-size: 13pt; color: #2563eb; margin-bottom: 40px; }
.cover-meta  { font-size: 9pt; color: #94a3b8; }
.cover-stats { display: table; width: 100%; margin: 40px auto; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
.cover-stat  { display: table-cell; text-align: center; padding: 16px; border-right: 1px solid #e2e8f0; }
.cover-stat:last-child { border-right: none; }
.stat-num    { font-size: 20pt; font-weight: bold; color: #2563eb; }
.stat-lbl    { font-size: 8pt; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; }
.warning-box { background: #fef3c7; border: 1px solid #f59e0b; border-radius: 6px; padding: 12px 16px; margin: 30px auto; max-width: 500px; font-size: 8.5pt; color: #92400e; }
.edition-pill { display: inline-block; background: #1e3a8a; color: #fff; border-radius: 14px; padding: 4px 12px; font-size: 8pt; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 18px; }
.copyright-page { padding: 22px 20px 0; }
.copyright-mark { font-size: 18pt; color: #1e3a8a; font-weight: bold; margin: 18px 0 10px; }
.rights-note { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 12px 14px; margin: 12px 0; }

/* ── Page elements ── */
.page-break { page-break-before: always; }
h1 { font-size: 16pt; font-weight: bold; color: #1e293b; border-bottom: 2px solid #2563eb; padding-bottom: 6px; margin-bottom: 16px; margin-top: 0; }
h2 { font-size: 11pt; font-weight: bold; color: #334155; margin: 16px 0 8px; }
h3 { font-size: 9.5pt; font-weight: bold; color: #475569; margin: 10px 0 6px; }
p  { margin-bottom: 8px; text-align: justify; }

/* ── Section headings ── */
.section-label { font-size: 7pt; font-weight: bold; color: #2563eb; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 4px; }

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
.badge-uni { background: #2563eb; color: #fff; }

/* ── Info boxes ── */
.info-box  { background: #f8fafc; border-left: 3px solid #2563eb; padding: 8px 12px; margin: 8px 0; border-radius: 0 4px 4px 0; }
.warn-box  { background: #fef9c3; border-left: 3px solid #f59e0b; padding: 8px 12px; margin: 8px 0; }
.rule-box  { background: #fff1f2; border-left: 3px solid #f43f5e; padding: 8px 12px; margin: 8px 0; }
code { font-family: DejaVu Sans Mono, Courier New, monospace; font-size: 7.5pt; background: #f1f5f9; padding: 1px 4px; border-radius: 3px; }

/* ── Module cards ── */
.module-card { border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 12px; overflow: hidden; }
.module-header { background: #f8fafc; padding: 8px 12px; border-bottom: 1px solid #e2e8f0; }
.module-body   { padding: 8px 12px; }
.module-name   { font-weight: bold; color: #1e293b; font-size: 9.5pt; }
.module-ctrl   { font-family: DejaVu Sans Mono, Courier; font-size: 7pt; color: #94a3b8; }
.tag { display: inline-block; background: #dbeafe; color: #1e40af; padding: 1px 6px; border-radius: 10px; font-size: 6.5pt; font-weight: bold; margin: 1px; }
.tag-amber { background: #fef3c7; color: #92400e; }
.tag-red   { background: #fee2e2; color: #991b1b; }
.tag-green { background: #dcfce7; color: #166534; }
.tag-blue  { background: #dbeafe; color: #1e40af; }
.status-pill { display: inline-block; padding: 1px 6px; border-radius: 10px; font-size: 6.5pt; font-weight: bold; margin: 1px; }

/* ── Architecture and workflow visuals ── */
.diagram { border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px; margin: 10px 0 16px; background: #f8fafc; page-break-inside: avoid; }
.diagram-row { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 0; }
.diagram-row td { border: 1px solid #bfdbfe; background: #fff !important; border-radius: 5px; padding: 10px 8px; text-align: center; vertical-align: middle; font-size: 8pt; }
.diagram-row .arrow { border: none; background: transparent !important; color: #2563eb; width: 4%; font-size: 14pt; font-weight: bold; padding: 0; }
.diagram-title { color: #1e3a8a; font-weight: bold; font-size: 9pt; margin-bottom: 3px; }
.diagram-note { color: #64748b; font-size: 7pt; }
.status-production { background: #dcfce7; color: #166534; }
.status-candidate { background: #fef3c7; color: #92400e; }
.status-internal { background: #dbeafe; color: #1e3a8a; }
.keep-together { page-break-inside: avoid; }

/* ── TOC ── */
.toc-entry { display: table; width: 100%; padding: 4px 0; border-bottom: 1px dotted #e2e8f0; font-size: 9pt; }
.toc-num   { display: table-cell; width: 28px; color: #2563eb; font-weight: bold; }
.toc-title { display: table-cell; }
.toc-dots  { display: table-cell; }
</style>
</head>
<body>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- COVER PAGE                                                              --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="cover">
  <div class="cover-logo">Philippine Science High School — Caraga Region Campus in Butuan City</div>
  <div class="edition-pill">Copyright Registration Edition</div>
  <div class="cover-title">Atlas</div>
  <div class="cover-sub">Comprehensive Technical Documentation · Edition {{ $document['edition'] }}</div>

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
      <div class="stat-num">70</div>
      <div class="stat-lbl">Documented Modules</div>
    </div>
  </div>

  <div class="warning-box">
    <strong>CONTROLLED REGISTRATION COPY</strong><br>
    Security-sensitive operational identifiers, credentials, personal data, and live user statistics are intentionally excluded.
  </div>

  <div class="cover-meta">
    Document ID {{ $document['document_id'] }}<br>
    Documentation Edition {{ $document['edition'] }} · Platform Version {{ $document['software_version'] }}<br>
    Generated: {{ $generated_at }}<br>
    Laravel {{ app()->version() }} · PHP {{ PHP_VERSION }} · MySQL 8.0
  </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- COPYRIGHT AND DOCUMENT CONTROL                                          --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="page-break copyright-page">
  <div class="section-label">Document Control</div>
  <h1>Copyright and Registration Record</h1>

  <div class="copyright-mark">Copyright © 2026 {{ $document['rights_holder'] }}</div>
  <p>All rights reserved. No part of this publication may be reproduced, stored, adapted, transmitted, or distributed in any form or by any means without authorization from the rights holder, except as permitted by applicable law.</p>

  <table style="margin-top:16px;">
    <tr><th style="width:30%">Control Field</th><th>Registered Value</th></tr>
    <tr><td><strong>Title</strong></td><td>Atlas Comprehensive Technical Documentation</td></tr>
    <tr><td><strong>Document ID</strong></td><td>{{ $document['document_id'] }}</td></tr>
    <tr><td><strong>Edition</strong></td><td>{{ $document['edition'] }} - Comprehensive Post-Release Edition</td></tr>
    <tr><td><strong>System Snapshot</strong></td><td>{{ $document['snapshot_date'] }}</td></tr>
    <tr><td><strong>Software Version</strong></td><td>{{ $document['software_version'] }}</td></tr>
    <tr><td><strong>Institutional Author</strong></td><td>{{ $document['rights_holder'] }}</td></tr>
    <tr><td><strong>Rights Holder</strong></td><td>{{ $document['rights_holder'] }}</td></tr>
    <tr><td><strong>Classification</strong></td><td>{{ $document['classification'] }}</td></tr>
    <tr><td><strong>Publication Status</strong></td><td>Final deposit copy after technical and visual quality assurance</td></tr>
  </table>

  <div class="rights-note">
    <strong>Statement of authorship and originality.</strong><br>
    This publication records the original selection, organization, explanation, and visual presentation of the Atlas platform's architecture, modules, workflows, integrations, data structures, security controls, and engineering conventions. It is an institutional technical work prepared from the implemented system and its maintained source materials.
  </div>

  <div class="info-box">
    <strong>Registration-copy security treatment.</strong><br>
    The document is comprehensive at the architectural and functional levels. Exact credentials, tokens, private keys, internal account identifiers, personal records, live user counts, and infrastructure endpoints that could increase operational risk are not reproduced. Their omission is deliberate and does not indicate that the corresponding controls or integrations are absent.
  </div>

  <h2>Edition History</h2>
  <table>
    <tr><th style="width:16%">Edition</th><th style="width:22%">Date</th><th>Description</th></tr>
    <tr><td>1.0</td><td>July 12, 2026</td><td>Initial comprehensive technical documentation: 59 modules, route catalogue, schema catalogue, permissions, conventions, infrastructure, and changelog.</td></tr>
    <tr><td>2.0</td><td>{{ $document['snapshot_date'] }}</td><td>Copyright registration edition with expanded module coverage, workflow architecture, security sanitization, release-status controls, technical QA, and all material post-July 12 additions.</td></tr>
  </table>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- TABLE OF CONTENTS                                                       --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="page-break">
  <h1>Table of Contents</h1>
  @foreach ([
    ['1', 'System Overview'],
    ['2', 'Module Reference  (70 modules in 6 groups)'],
    ['3', 'Workflow and Integration Architecture'],
    ['4', 'Routes / API Reference  (' . number_format(count($routes)) . ' named routes)'],
    ['5', 'Database Schema  (' . count($schema) . ' tables)'],
    ['6', 'Permissions Reference  (' . $stats['permissions'] . ' permissions)'],
    ['7', 'Engineering Conventions'],
    ['8', 'Infrastructure, Security, and Continuity'],
    ['9', 'Verification and Quality Assurance'],
    ['10', 'Changelog and Original Contributions'],
    ['11', 'Glossary'],
    ['12', 'AI Usage Statement'],
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
  <p>Atlas (formerly CRCMIS / BugSayMis) is the unified digital campus management platform of the Philippine Science High School - Caraga Region Campus in Butuan City (PSHS-CRC). It consolidates institutional workflows that were previously fragmented across paper forms, spreadsheets, stand-alone applications, and manual routing. The platform covers human resources, payroll, performance management, curriculum and instruction, student services, records, procurement, property, service requests, communications, analytics, mobile access, device management, and operational monitoring.</p>

  <div class="info-box">
    <strong>Edition scope.</strong> This edition documents the production baseline through August 2, 2026 and separately identified release-candidate artifacts present in the technical snapshot. Functionality not yet released is explicitly marked <strong>Release Candidate</strong> and is not represented as a production capability. Aggregate route, schema, and permission counts include those labeled artifacts so the appendices remain internally complete.
  </div>

  <h2>Platform Architecture</h2>
  <div class="diagram">
    <table class="diagram-row">
      <tr>
        <td><div class="diagram-title">Employees and Administrators</div><div class="diagram-note">Browser-based Inertia application</div></td>
        <td class="arrow">→</td>
        <td><div class="diagram-title">Students and Parents</div><div class="diagram-note">Student Portal and AtlasGo mobile application</div></td>
        <td class="arrow">→</td>
        <td><div class="diagram-title">Devices and Kiosks</div><div class="diagram-note">Atlas Sentinel, biometrics, scanners, NFC and QR</div></td>
      </tr>
    </table>
    <table class="diagram-row">
      <tr>
        <td><div class="diagram-title">Cloud Edge</div><div class="diagram-note">DNS, TLS, WAF, request filtering, rate limiting</div></td>
        <td class="arrow">→</td>
        <td><div class="diagram-title">Application Services</div><div class="diagram-note">Laravel, Vue/Inertia, APIs, queues, scheduler, real-time events</div></td>
        <td class="arrow">→</td>
        <td><div class="diagram-title">Managed Data Services</div><div class="diagram-note">Relational database, cache, private object storage, observability</div></td>
      </tr>
    </table>
    <table class="diagram-row">
      <tr>
        <td><div class="diagram-title">Institutional Integrations</div><div class="diagram-note">Google Workspace, email, SMS, Firebase, identity providers</div></td>
        <td class="arrow">↔</td>
        <td><div class="diagram-title">Cross-Module Services</div><div class="diagram-note">Approval Inbox, digital signatures, QR verification, notifications, audit events</div></td>
        <td class="arrow">↔</td>
        <td><div class="diagram-title">Operational Governance</div><div class="diagram-note">Module Monitor, WatchTower, backups, release controls</div></td>
      </tr>
    </table>
  </div>

  <h2>Technology Stack</h2>
  <table>
    <tr><th>Layer</th><th>Technology</th></tr>
    @foreach ([
      ['Backend',          'Laravel 12 · PHP 8.4 (FPM)'],
      ['Frontend',         'Vue 3 (script setup) · Inertia.js 2 · Vite 7'],
      ['Styling',          'Tailwind CSS 3 · Heroicons 2'],
      ['Database',         'MySQL 8.0 on a KMS-encrypted managed relational database with Multi-AZ resilience and deletion protection'],
      ['Cache / Queue',    'Redis 7.0 (AWS ElastiCache replication group)'],
      ['Real-time',        'Soketi (self-hosted Pusher) · Laravel Echo · Pusher JS SDK'],
      ['Web Push',         'minishlink/web-push v9 · VAPID · FCM · atlas-sw.js'],
      ['File Storage',     'Private AWS S3 object storage with Block Public Access; authenticated delivery through application proxy routes'],
      ['PDF',              'mPDF 8 (tempDir = sys_get_temp_dir())'],
      ['Excel',            'PhpSpreadsheet · Maatwebsite Excel'],
      ['Container',        'Docker · AWS ECS Fargate — web task (nginx edge + app + soketi + ADOT sidecars) + separate worker service (cron + queue + Pulse)'],
      ['Observability',    'AWS X-Ray + OpenTelemetry (ADOT collector sidecar, OTLP :4318) · Laravel Pulse (worker service)'],
      ['CI/CD',            'GitHub Actions → ECR (immutable tags) → ECS native blue/green deploy'],
      ['CDN / WAF',        'Cloudflare (orange-cloud proxy, WAF active)'],
      ['Secrets',          'AWS Systems Manager Parameter Store and AWS Secrets Manager; values injected into tasks at runtime'],
    ] as [$layer, $tech])
    <tr><td><strong>{{ $layer }}</strong></td><td>{{ $tech }}</td></tr>
    @endforeach
  </table>

  <h2>Production Infrastructure</h2>
  <table>
    <tr><th>Resource</th><th>Detail</th></tr>
    @foreach ([
      ['Application',     'Public HTTPS application protected by Cloudflare and an AWS Application Load Balancer'],
      ['Web Service',     'ECS Fargate blue/green service with independently scalable application tasks'],
      ['Worker Service',  'Dedicated ECS service for the scheduler, queue processing, and Pulse telemetry'],
      ['Container Registry', 'Private Amazon ECR repositories with immutable release tags and scan-on-push'],
      ['Database',        'KMS-encrypted Multi-AZ relational database with deletion protection'],
      ['Cache',           'Managed Redis replication group for cache, queues, broadcasts, and coordination'],
      ['Object Storage',  'Private regional object-storage bucket with Block Public Access'],
      ['Transport',       'TLS at the public edge and re-encrypted transport from the load balancer to application targets'],
      ['Health Check',    'A dedicated status endpoint is used by the load balancer and orchestration platform'],
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
    <strong>Operational command policy.</strong><br>
    Development commands run inside the dedicated PHP container. Production commands require authenticated ECS Exec access, an authorized task role, and audit logging. Exact production resource identifiers are maintained in the restricted operations runbook and intentionally omitted from this registration copy.
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
      ['Online Time Punch',         'hr.online-punch.*|hr.face-enrollment.*', 'HR\\OnlineTimePunchController, HR\\FaceEnrollmentController', 'Employee-operated time punches using enrolled facial references, location accuracy, configurable geofence zones, trusted-network policy, independent time-in/out actions, HR enrollment review, monitoring, and DTR integration. Images use base64 JSON and private S3 storage.', ['hr.online-punch.record','hr.online-punch.monitor','hr.online-punch.manage-geofence','hr.face-enrollment.self','hr.face-enrollment.manage']],
      ['Gate Pass',                 'gatepass.*',                    'HumanResource\\GatePassController',          '3-stage gate pass flow with printable form. DTR deduction is derived live from approved passes, so it survives DTR regeneration. OCD division bypasses Division Chief approval.', []],
      ['Employee Digital ID',       'employee-id.*',                 'EmployeeIdController',                       'Wallet-style digital employee ID with opaque-token QR code and public live-status verification page. Revocable per employee; includes once-per-login digital signature setup prompt.', []],
      ['HR Service Records',        'hr.service-records.*',          'HR\\ServiceRecordController',                'Authoritative chronological employee service history with PDS work-experience import, versioned entries, appointment and compensation details, leave-without-pay periods, documentary evidence, verification and supersession controls, action history, own-record access, and CSV export.', ['hr.service-records.view','hr.service-records.view-own','hr.service-records.manage','hr.service-records.verify','hr.service-records.certify','hr.service-records.export']],
      ['HR Document Requests',      'hr.document-requests.*',        'HR\\HrDocumentRequestController, HR\\HrDocumentVerificationController', 'Employee self-service requests for employment, compensation, service-record, leave-credit, last-salary, government-service, administrative-case, and custom certifications. HR triage and processing use SLA targets, audit events, private attachments, control numbers, PDF generation, QR verification, and digital delivery or pickup. Documents requiring the Office of the Campus Director are routed into the shared Approval Inbox for PIN signing.', ['hr.document-requests.file','hr.document-requests.process','hr.document-requests.issue','hr.document-requests.manage-types','hr.document-requests.view-reports','hr.document-requests.ocd-approve']],
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
      ['Faculty Loading',           'faculty-loading.*',             'FacultyLoading\\*',                          'School-year-scoped workload and schedule management. Deterministic conflict-free placement with manual drag-and-drop and live conflict detection, covering science-core/elective groups, independent-learning periods, and configurable bell schedules with section overrides. Also handles research and committee assignments, official-time synchronization, overload computation, and formal print outputs.', ['faculty_loading.manage','faculty_loading.approve']],
      ['Schedule Analytics & Governance', 'faculty-loading.schedule-analytics.*|faculty-loading.schedules.*', 'FacultyLoading\\ScheduleAnalyticsController, ClassScheduleApprovalController, ClassScheduleDayAdjustmentController', 'Quantitative load-balance and schedule-quality analytics plus controlled schedule approvals, amendments, swap requests, multi-level undo, assignment and schedule version compare/restore, collaborative scope locks, and temporary adjusted-day schedules for suspensions, local events, or shortened class days.', ['faculty_loading.manage','faculty_loading.approve']],
      ['Class Records',             'class-records.*',               'ClassRecord\\*',                             'Quarterly assessment and grade recording. Supports multi-subject creation, category-labeled sibling records, PEHM co-teaching, unit-scoped grading options, compliance mode, and Independent Learning Activity grading. Also tracks attendance and uniform detail with clinic-verified excused cutting, computes annual grades, and provides a reversible archive, school-year locks, monitoring, and A3 PDF export.', ['class-records.view','class-records.manage']],
      ['Weekly Assessment Tracker','class-records.wat.*',            'ClassRecord\\WeeklyAssessmentTrackerController', 'Calendar-based assessment plotting with direct teacher scheduling, shared caps for grouped subjects, quarter-exam exemptions, ACIDAA-controlled deletion requests, per-faculty compliance tracking, CID review, monitoring analytics, and server-rendered multi-page PDF output.', ['class-records.view','class-records.manage']],
      ['Alternative Learning Program','alp.*',                       'ALP\\AlpController, ALP\\AlpPdfController', 'Accreditation and governance for student organizations and alternative learning programs. Covers annual cycles, membership and officers, and adviser/coordinator/Registrar/OCD review of activities, attendance, financial entries, and documentary requirements. Produces controlled reports and compliance scoring, backed by an immutable audit history, AMS synchronization, and formal PDF outputs.', ['alp.view','alp.manage','alp.advise','alp.coordinate','alp.registrar-certify','alp.approve','alp.reports','alp.audit']],
      ['Homeroom Advisory Attendance','homeroom-attendance.*',       'HomeroomAttendance\\*',                      'Advisory-class attendance covering daily records, school activities, Flag Ceremony and Retreat, subject-attendance synchronization, deduction settings, monthly Record on Attendance and Punctuality, Homeroom Coordinator review, audit logs, and Registrar-issued Class Admission Slips with protected document delivery.', ['homeroom-attendance.log','homeroom-attendance.admission-slip.issue','homeroom-attendance.coordinator-review','homeroom-attendance.admin','homeroom-attendance.settings.manage']],
      ['Academic Calendar',         'academic-calendar.*',           'SchoolCalendarController',                    'CID-managed calendar for holidays, suspensions, institutional activities, and instructional exceptions consumed by homeroom attendance, schedule adjustments, and other date-sensitive academic workflows.', ['academic-calendar.manage']],
      ['Teacher Attendance',        'teacher-attendance.*|class-tap*','TeacherAttendanceController', 'Classroom-level faculty presence monitoring using printable NFC classroom cards and a location/network-gated QR fallback. Tap logs record channel and location context and feed attendance monitoring and export.', []],
      ['Live Quiz',                 'quiz.*',                        'Quiz\\QuizController, QuizSessionController, QuizPlayController', 'Kahoot-style live quizzes: hosted sessions with join codes, reveal-gated answers, streaks and 2× multiplier, get-ready intros, sounds, and post-session reports.', ['quiz.manage']],
      ['Science Lab Management',    'science-lab.*',                 'ScienceLab\\*',                              'CIM 4.4 — laboratories, equipment and reagent requests with endorsement/approval, reservations, calibration schedules, consumables, maintenance, waste disposal, safety, and reports.', ['lab.manage','lab.request','lab.calibration.approve']],
      ['Computer Lab Management',   'computer-labs.*',               'ComputerLabController, ComputerLabScheduleApprovalController', 'Computer laboratory inventory and weekday scheduling with conflict validation, per-lab calendars, formal printable schedules, approval workflow, subject color coding, booking transfers, guarded room swaps, and equipment-room integrity controls.', []],
      ['Competitions & Winnings',   'cid.competitions.*',            'CID\\CompetitionController',                 'Employee and student competition entries with coach/co-coach and per-person awards. Optional auto-filed Graphic Design IT Job Request on creation.', ['cid.competitions.view','cid.competitions.manage']],
      ['Activity Management',       'ams.*',                         'AMS\\*',                                     'Activity proposals (in-house vs training flows), co-proponents, monitoring dashboard, certificates generation, and post-activity evaluations.', ['activities.manage','activities.monitor']],
    ],
    'Registrar & Student Services' => [
      ['Student Information System','registrar.*|students.*',        'Registrar\\*, StudentController',            'Enrollment periods and applications (enroll-then-assign-section), bulk section assignment by grade level, promotions, report cards, transcripts, retention policies, analytics. /students admin datatable with inline edit, canvas photo crop, and CR-80 ID card printing.', ['students.manage','students.analytics.view']],
      ['Student Gate Attendance',   'students.attendance.*',         'StudentAttendance\\*',                       'Gate attendance using PISAY ID barcodes with parent SMS notifications, audited guard directory, PIN-secured kiosk access, registered kiosk devices, front/rear camera selection, USB barcode scanning, protected self-scan, operator audit logs, and optimized scan capture.', ['students.attendance.scan','students.attendance.view']],
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
      ['Official Issuances',        'issuances.*',                   'IssuanceController',                         'Control-numbered issuances with Tiptap authoring, supplements linked to parent issuances, lifecycle archiving, QR stamping, PIN signing, queued PDF and email delivery, full-text search with document-extraction fallback, and cryptographic tamper checks on the public verification page.', ['issuances.view','issuances.manage']],
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
      ['Atlas Sentinel',            'atlas-sentinel.*|ict-equipments.*|api/ict-agent/*', 'ICTEquipmentController, AtlasSentinelRemoteHelpController, AtlasSentinelBackupController, Api\\BiometricPunchIngestController', 'ICT fleet management and endpoint telemetry: equipment inventory, hardware/software snapshots, network presence, health scoring, alert escalation, controlled remediation, self-healing rules, signed agent releases, attended remote help, scheduled document backups, and a registered-device biometric bridge that ingests Granding terminal punches into DTR with a live broadcast feed.', ['it.equipment.view','it.equipment.manage','atlas.sentinel.remote-help.manage','hr.biometric.monitor']],
      ['Atlas WatchTower',          'atlas.watchtower.*',            'Atlas\\AtlasWatchTowerController',           'Live application telemetry dashboard — requests, slow queries, queue jobs, exceptions, active users. Reads AWS CloudWatch metrics and X-Ray traces via the app\'s ECS Task Role. Built after the LaraOwl incident for real-time admin visibility into infra health.', ['atlas.watchtower.view','atlas.watchtower.manage']],
      ['Atlas Module Monitor',      'atlas.modules.*',               'Atlas\\AtlasModuleController',               'Per-module health, maturity scoring, radar chart, and live usage metrics across the whole platform.', ['atlas.modules.view']],
      ['Google Workspace Lifecycle','atlas.workspace-sync.*',        'Atlas\\WorkspaceSyncController, GoogleWorkspaceDirectoryService, Atlas\\WorkspaceProvisioningService', 'Directory reconciliation and identity lifecycle management for official employee and student accounts. The module scans for discrepancies, produces reviewable correction proposals, supports approve/reject actions, auto-provisions accounts during admission or hiring, and suspends or reactivates accounts when employment status changes.', ['atlas.workspace-sync.view','atlas.workspace-sync.manage']],
      ['Dyna AI Assistant',         'api/dyna/*',                    'Api\\DynaAuthController, Api\\DynaController, Atlas\\Dyna\\DynaOrchestratorService', 'Permission-gated conversational assistant backed by Amazon Bedrock. Sanctum-authenticated clients use stored conversations, a bounded tool registry, iterative tool-use orchestration, and auditable read-only tools for institutional headcount and leave-trend analysis. The macOS client remains separately release-gated.', ['atlas.dyna.access'], 'Release Candidate'],
      ['Profile',                   'profile.*',                     'ProfileController',                          'Self-service profile — name, specialization, base64 photo to S3 (profile_pictures/), digital signature management. Email and password are not user-editable (HR-managed / Google OAuth).', []],
    ],
  ] as $groupName => $cards)
  <h2 style="margin-top:18px; border-bottom:1px solid #e2e8f0; padding-bottom:4px;">{{ $groupName }}</h2>
  @foreach ($cards as $card)
  @php
    [$name, $route, $ctrl, $desc, $perms] = array_slice($card, 0, 5);
    $status = $card[5] ?? 'Production';
  @endphp
  <div class="module-card">
    <div class="module-header">
      <span class="module-name">{{ $name }}</span>
      <span class="module-ctrl"> · {{ $ctrl }}</span>
      <span class="status-pill {{ $status === 'Production' ? 'status-production' : 'status-candidate' }}" style="float:right;">{{ $status }}</span>
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
{{-- 3. WORKFLOW AND INTEGRATION ARCHITECTURE                                --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="page-break">
  <div class="section-label">Section 3</div>
  <h1>Workflow and Integration Architecture</h1>

  <h2>Shared Approval and Digital-Signing Pattern</h2>
  <p>Atlas modules retain their own domain rules while delegating executive actions to a shared approval surface. This preserves module ownership, prevents duplicate approval interfaces, and provides a consistent PIN-signing and audit experience.</p>
  <div class="diagram">
    <table class="diagram-row">
      <tr>
        <td><div class="diagram-title">Originating Module</div><div class="diagram-note">Validates filing rules and creates a domain record</div></td>
        <td class="arrow">→</td>
        <td><div class="diagram-title">Routing Policy</div><div class="diagram-note">Determines approver, sequence, segregation of duties, and required signature</div></td>
        <td class="arrow">→</td>
        <td><div class="diagram-title">Approval Inbox</div><div class="diagram-note">Presents authorized pending work in one queue</div></td>
      </tr>
    </table>
    <table class="diagram-row">
      <tr>
        <td><div class="diagram-title">PIN Verification</div><div class="diagram-note">Confirms the authorized signatory at the point of action</div></td>
        <td class="arrow">→</td>
        <td><div class="diagram-title">Domain Transition</div><div class="diagram-note">Applies an atomic status change and records actor/time/context</div></td>
        <td class="arrow">→</td>
        <td><div class="diagram-title">Issued Artifact</div><div class="diagram-note">PDF, signature, control number, QR verification, notification, or downstream record</div></td>
      </tr>
    </table>
  </div>

  <h2>HR Document Request Lifecycle</h2>
  <div class="diagram">
    <table class="diagram-row">
      <tr>
        <td><div class="diagram-title">1. Employee Filing</div><div class="diagram-note">Select type, state purpose and delivery mode, attach evidence through base64 JSON</div></td>
        <td class="arrow">→</td>
        <td><div class="diagram-title">2. HR Triage</div><div class="diagram-note">Accept, request compliance, reject, assign processor, and track SLA</div></td>
        <td class="arrow">→</td>
        <td><div class="diagram-title">3. Preparation</div><div class="diagram-note">Generate controlled content from authoritative HR records</div></td>
      </tr>
    </table>
    <table class="diagram-row">
      <tr>
        <td><div class="diagram-title">4A. HR Authority</div><div class="diagram-note">HR-authorized documents proceed to issuance</div></td>
        <td class="arrow">or</td>
        <td><div class="diagram-title">4B. OCD Approval</div><div class="diagram-note">Signable requests enter the shared Approval Inbox for PIN approval</div></td>
        <td class="arrow">→</td>
        <td><div class="diagram-title">5. Controlled Issuance</div><div class="diagram-note">Control number, signed PDF, QR token, audit event, digital delivery or pickup</div></td>
      </tr>
    </table>
  </div>

  <h2>Service-Record Provenance Model</h2>
  <p>Service records are maintained as evidence-backed historical facts rather than editable free-form certificates. Each entry retains its version history, verification state, evidence, and any superseding entry. Certification draws only from the authorized record set, ensuring that an issued Service Record can be traced to the source facts reviewed by HR.</p>
  <table>
    <tr><th style="width:22%">Layer</th><th style="width:31%">Primary Responsibility</th><th>Control</th></tr>
    <tr><td>Service Entry</td><td>Appointment, position, status, salary, station, service dates, and separation context</td><td>Structured validation and chronology checks</td></tr>
    <tr><td>Evidence</td><td>Appointment papers, certifications, or other supporting documents</td><td>Private S3 storage and authenticated proxy delivery</td></tr>
    <tr><td>Verification</td><td>HR confirmation that an entry and its evidence are sufficient</td><td>Named permission and auditable actor/time</td></tr>
    <tr><td>Version/Supersession</td><td>Correction without silently rewriting history</td><td>Prior values retained; replacement linkage recorded</td></tr>
    <tr><td>Certification</td><td>Generation of an official employee-facing record</td><td>Restricted permission and document-request approval policy</td></tr>
  </table>
</div>

<div class="page-break">
  <div class="section-label">Section 3 · Continued</div>
  <h1>Academic and Identity Data Flows</h1>

  <h2>Academic Attendance Synchronization</h2>
  <div class="diagram">
    <table class="diagram-row">
      <tr>
        <td><div class="diagram-title">Enrollment and Section Assignment</div><div class="diagram-note">Current-school-year roster authority</div></td>
        <td class="arrow">→</td>
        <td><div class="diagram-title">Subject Class Records</div><div class="diagram-note">Attendance, ILA, assessment dates, grading, uniform and cutting status</div></td>
        <td class="arrow">→</td>
        <td><div class="diagram-title">Homeroom Attendance</div><div class="diagram-note">Daily advisory view, activity logs, monthly report and coordinator review</div></td>
      </tr>
    </table>
    <table class="diagram-row">
      <tr>
        <td><div class="diagram-title">Clinic Consultation</div><div class="diagram-note">Verified clinic slip can excuse an eligible cutting record</div></td>
        <td class="arrow">↔</td>
        <td><div class="diagram-title">Class Admission Slip</div><div class="diagram-note">Registrar-controlled return-to-class document</div></td>
        <td class="arrow">↔</td>
        <td><div class="diagram-title">Academic Calendar</div><div class="diagram-note">Holidays, suspensions, activities and date-sensitive instructional exceptions</div></td>
      </tr>
    </table>
  </div>

  <h2>Google Workspace Identity Lifecycle</h2>
  <div class="diagram">
    <table class="diagram-row">
      <tr>
        <td><div class="diagram-title">Admission or Hiring</div><div class="diagram-note">Validated person record becomes the identity source</div></td>
        <td class="arrow">→</td>
        <td><div class="diagram-title">Provisioning</div><div class="diagram-note">Official account is created under controlled organizational policy</div></td>
        <td class="arrow">→</td>
        <td><div class="diagram-title">Directory Reconciliation</div><div class="diagram-note">Scans propose corrections; authorized users approve or reject</div></td>
        <td class="arrow">→</td>
        <td><div class="diagram-title">Status Lifecycle</div><div class="diagram-note">Employment status suspends or reactivates the linked account</div></td>
      </tr>
    </table>
  </div>

  <h2>Atlas Sentinel Biometric Bridge</h2>
  <div class="diagram">
    <table class="diagram-row">
      <tr>
        <td><div class="diagram-title">Biometric Terminal</div><div class="diagram-note">Produces clock records on the protected campus network</div></td>
        <td class="arrow">→</td>
        <td><div class="diagram-title">Registered Sentinel Agent</div><div class="diagram-note">Parses terminal output and authenticates as an enrolled device</div></td>
        <td class="arrow">→</td>
        <td><div class="diagram-title">Ingest API</div><div class="diagram-note">Validates device, employee, event time, idempotency, and source</div></td>
        <td class="arrow">→</td>
        <td><div class="diagram-title">DTR and Live Feed</div><div class="diagram-note">Creates attendance facts and broadcasts a monitoring event</div></td>
      </tr>
    </table>
  </div>

  <h2>Cross-Module Integration Matrix</h2>
  <table>
    <tr><th style="width:22%">Source</th><th style="width:22%">Destination</th><th style="width:26%">Transferred Fact</th><th>Control</th></tr>
    <tr><td>Leave / WFH / Gate Pass</td><td>DTR</td><td>Approved absence or time adjustment</td><td>Derived synchronization; approval status required</td></tr>
    <tr><td>Enrollment</td><td>Class Records / Homeroom</td><td>Current roster, grade and section</td><td>Current school year is authoritative</td></tr>
    <tr><td>Class Records</td><td>Homeroom Attendance</td><td>Subject attendance and eligible infractions</td><td>Idempotent, date- and quarter-scoped synchronization</td></tr>
    <tr><td>Clinic</td><td>Class Records</td><td>Verified medical excuse</td><td>Student identity and consultation linkage</td></tr>
    <tr><td>Faculty Loading / HR Schedule</td><td>DTR / Teacher Attendance</td><td>Official time, lunch and teaching schedule</td><td>Approved schedule and school-year scope</td></tr>
    <tr><td>ALP</td><td>Activity Management</td><td>Approved program activities</td><td>Explicit integration service and audit history</td></tr>
    <tr><td>HR Document Requests</td><td>Approval Inbox</td><td>OCD-signable document approval</td><td>Permission, stage sequence and PIN verification</td></tr>
    <tr><td>Atlas Sentinel</td><td>DTR / IT Job Requests</td><td>Biometric punches or health incidents</td><td>Device enrollment, validation and escalation policy</td></tr>
  </table>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- 4. ROUTES                                                               --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="page-break">
  <div class="section-label">Section 4</div>
  <h1>Routes / API Reference</h1>
  <p style="margin-bottom:10px;">Named routes only ({{ number_format(count($routes)) }} total). Sorted by URI. HEAD methods excluded.</p>

  <table>
    <thead>
    <tr>
      <th style="width:10%">Method</th>
      <th style="width:30%">URI</th>
      <th style="width:25%">Route Name</th>
      <th style="width:35%">Controller</th>
    </tr>
    </thead>
    <tbody>
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
    </tbody>
  </table>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- 5. DATABASE SCHEMA                                                      --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="page-break">
  <div class="section-label">Section 5</div>
  <h1>Database Schema</h1>
  <p style="margin-bottom:10px;">{{ count($schema) }} tables. PK = Primary Key · MUL = Foreign Key Index · UNI = Unique.</p>
  <div class="info-box" style="margin-bottom:10px;">
    The schema catalogue reflects the complete Edition {{ $document['edition'] }} technical snapshot. Tables prefixed <code>dyna_</code> belong to the Dyna AI Assistant release candidate identified in the Module Reference.
  </div>

  @foreach($schema as $table)
  <h3 style="margin-top:14px; font-family: DejaVu Sans Mono; font-size:9pt;">
    {{ $table['name'] }}
    <span style="font-weight:normal; color:#94a3b8; font-size:7.5pt;">({{ count($table['columns']) }} cols)</span>
  </h3>
  <table>
    <thead>
    <tr>
      <th style="width:22%">Column</th>
      <th style="width:18%">Type</th>
      <th style="width:7%">Null</th>
      <th style="width:7%">Key</th>
      <th style="width:10%">Extra</th>
      <th style="width:36%">Foreign Key</th>
    </tr>
    </thead>
    <tbody>
    @foreach($table['columns'] as $col)
    <tr class="{{ $col['key'] === 'PRI' ? 'pk' : ($col['fk'] ? 'fk' : '') }}">
      <td style="font-family: DejaVu Sans Mono; font-size:7.5pt; font-weight: {{ $col['key'] === 'PRI' ? 'bold' : 'normal' }};">{{ $col['name'] }}</td>
      <td style="font-family: DejaVu Sans Mono; font-size:7pt; color:#2563eb;">{{ $col['type'] }}</td>
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
    </tbody>
  </table>
  @endforeach
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- 6. PERMISSIONS                                                          --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="page-break">
  <div class="section-label">Section 6</div>
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
{{-- 7. ENGINEERING CONVENTIONS                                              --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="page-break">
  <div class="section-label">Section 7</div>
  <h1>Engineering Conventions</h1>

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
{{-- 8. INFRASTRUCTURE                                                       --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="page-break">
  <div class="section-label">Section 8</div>
  <h1>Infrastructure, Security, and Continuity</h1>

  <h2>CI/CD Pipeline</h2>
  <ol style="margin-left:16px; margin-bottom:10px;">
    <li>Developer pushes to <code>main</code> branch</li>
    <li>GitHub Actions builds the application and edge images with versioned frontend configuration</li>
    <li>Images pushed to ECR with immutable tags (commit SHA)</li>
    <li>A pre-deploy migration runs once in an isolated Fargate task; the release stops before traffic changes if migration fails</li>
    <li>The web task definition is updated and rolled out using native ECS blue/green deployment</li>
    <li>The dedicated worker service is rolled separately after the web deployment to prevent duplicate scheduled or queued work during overlap</li>
    <li>The pipeline monitors the service deployment until success or automatic rollback reaches a terminal state</li>
    <li>At startup, runtime secrets are fetched through managed AWS services before the process supervisor launches the assigned web or worker role</li>
  </ol>

  <h2>Secrets Management</h2>
  <table>
    <tr><th>Secret</th><th>Location</th></tr>
    <tr><td>Database, mail, cache, web-push, and object-storage credentials</td><td>Encrypted parameter store; task-role access only</td></tr>
    <tr><td>External service-account documents</td><td>Encrypted secrets service; retrieved only at runtime</td></tr>
    <tr><td>Public frontend keys</td><td>Explicitly classified public values embedded at build time; private counterparts remain server-side</td></tr>
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

<div class="page-break">
  <h2>Data Protection and Continuity</h2>
  <table>
    <tr><th style="width:23%">Control Area</th><th>Implemented Protection</th></tr>
    <tr><td>Data at rest</td><td>KMS-encrypted relational storage, encrypted object storage, private bucket policy, and encrypted managed cache.</td></tr>
    <tr><td>Data in transit</td><td>HTTPS at the public edge, encrypted load-balancer-to-target transport, authenticated application sessions, and signed service requests.</td></tr>
    <tr><td>Access control</td><td>Google OAuth, Sanctum for scoped APIs, many-to-many RBAC, module permissions, role bypass limited to the Administrator role, and server-side authorization on mutations.</td></tr>
    <tr><td>Document integrity</td><td>Control numbers, QR verification tokens, digital signatures, PIN confirmation, private storage, and tamper checks where the document type requires them.</td></tr>
    <tr><td>Auditability</td><td>Domain event histories, approval actors and timestamps, CloudTrail, application logs, request metrics, distributed traces, and endpoint health history.</td></tr>
    <tr><td>Backup</td><td>Scheduled compressed database backup to protected cloud storage, verification of backup recency, and scheduled Sentinel document backups for managed endpoints.</td></tr>
    <tr><td>Release recovery</td><td>Immutable container tags, health-gated blue/green cutover, automatic rollback, additive migration discipline, and separately rolled worker processes.</td></tr>
    <tr><td>Privacy</td><td>Authenticated proxy delivery, minimal exposure in public verification pages, exclusion of personal data from technical documentation, and no direct public object-storage URLs.</td></tr>
  </table>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- 9. VERIFICATION AND QUALITY ASSURANCE                                   --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="page-break">
  <div class="section-label">Section 9</div>
  <h1>Verification and Quality Assurance</h1>

  <h2>Layered Test Strategy</h2>
  <table>
    <tr><th style="width:20%">Layer</th><th style="width:32%">Purpose</th><th>Representative Evidence</th></tr>
    <tr><td>Unit</td><td>Validate pure business rules and deterministic services</td><td>Grade computation, maturity scoring, parsers, schedule constraints, name formatting, and domain calculations</td></tr>
    <tr><td>Feature</td><td>Validate authenticated workflows, authorization, validation, state transitions, and persistence</td><td>Document requests, service records, Dyna APIs, Sentinel enrollment, Class Record, attendance, and approval workflows</td></tr>
    <tr><td>Integration</td><td>Validate cross-module and external-service boundaries</td><td>Workspace synchronization, biometric ingestion, notification dispatch, S3 proxy delivery, and approval-inbox routing</td></tr>
    <tr><td>Frontend build</td><td>Validate Vue component compilation, imports, asset bundling, and route helpers</td><td>Vite production build and lint/format checks applicable to changed files</td></tr>
    <tr><td>Document rendering</td><td>Validate generated PDFs and formal outputs</td><td>Text extraction, metadata inspection, full-page PNG rendering, visual review, signature and QR placement checks</td></tr>
    <tr><td>Deployment</td><td>Validate release health and rollback safety</td><td>Migration task status, blue/green deployment state, health checks, target health, traffic shift, and error alarms</td></tr>
  </table>

  <h2>Module Acceptance Controls</h2>
  <table style="border:none; margin-bottom:0;">
    <tr>
      <td style="width:48%; padding:0 10px 0 0; border:none; vertical-align:top;">
        <h3 style="font-size:9.5pt; font-weight:bold; color:#475569; margin:0 0 6px;">Security and Authorization</h3>
        <ul style="margin-left:16px;">
          <li>Every protected route has authentication and appropriate permission enforcement.</li>
          <li>Negative authorization paths are tested for unauthorized roles.</li>
          <li>Approval actors cannot self-approve or skip required sequence unless a documented policy allows it.</li>
          <li>Uploads use validated base64 JSON and private object storage.</li>
        </ul>
      </td>
      <td style="width:48%; padding:0 0 0 10px; border:none; vertical-align:top;">
        <h3 style="font-size:9.5pt; font-weight:bold; color:#475569; margin:0 0 6px;">Data and Workflow Integrity</h3>
        <ul style="margin-left:16px;">
          <li>Transactions protect multi-record state transitions.</li>
          <li>Cross-module synchronization is idempotent and school-year/date scoped.</li>
          <li>Historical facts use versioning, archive, or supersession instead of silent deletion.</li>
          <li>Printed artifacts use authoritative signatories and formatted names.</li>
        </ul>
      </td>
    </tr>
  </table>

  <h2>Documentation Release Gate</h2>
  <ol style="margin-left:16px;">
    <li>Generate from the intended application revision and verified database snapshot.</li>
    <li>Confirm route, table, permission, module, edition, and metadata counts.</li>
    <li>Search for credentials, tokens, private keys, personal data, and prohibited infrastructure identifiers.</li>
    <li>Render every PDF page to an image and inspect for clipping, overlap, broken tables, missing glyphs, and inconsistent headers or footers.</li>
    <li>Verify that all post-July 12 modules appear in the Module Reference and Changelog.</li>
    <li>Compute and retain a SHA-256 fingerprint for the final deposit artifact.</li>
  </ol>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- 10. CHANGELOG AND ORIGINAL CONTRIBUTIONS                               --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="page-break">
  <div class="section-label">Section 10</div>
  <h1>Changelog and Original Contributions</h1>

  <div class="info-box" style="margin-bottom:12px;">
    The platform version is <strong>1.0.0</strong> (Initial Production Release, May 2, 2026 — see <code>app_versions</code>).
    Post-release changes are listed below as dated updates rather than version bumps.
  </div>

  @foreach ([
    ['August 2026 Update', 'August 2026', [
      'HR Document Requests: employee filing, configurable certification catalogue, SLA tracking, HR processing, private evidence, control-numbered PDFs, QR verification, digital delivery/pickup, and OCD Approval Inbox signing for documents requiring executive authority',
      'HR Service Records: evidence-backed chronological service history, PDS import, versioning, verification, supersession, action history, leave-without-pay periods, own-record access, and controlled export/certification',
      'Alternative Learning Program: annual program cycles, accreditation workflow, memberships and officers, academic certification, activities and attendance, financial entries, compliance reports, QMS controls, audit history, and AMS integration',
      'Homeroom Advisory Attendance: daily, activity, Flag Ceremony/Retreat, monthly attendance and punctuality reporting, Coordinator review, Class Admission Slips, and Academic Calendar integration',
      'Faculty Loading: schedule analytics, approval and amendment workflow, swap requests, collaborative scope locks, schedule/load versions, bell-schedule overrides, science-core/elective/ILA scheduling, and adjusted-day class schedules',
      'Class Records: Weekly Assessment Tracker, direct calendar plotting, CID monitoring, ACIDAA deletion approval, ILA grading, multi-subject and category-labeled records, PEHM co-teaching, Values Education compliance grading, detailed attendance/uniform remarks, and clinic-verified excused cutting',
      'Google Workspace identity lifecycle: discrepancy proposals, directory reconciliation, admission/hire provisioning, and employment-status suspension/reactivation',
      'Atlas Sentinel biometric bridge: registered-device ingestion of Granding terminal punches, DTR synchronization, and live HR monitoring feed',
      'Computer Laboratory scheduling: per-lab calendars, schedule approval, formal print, booking transfer, and guarded room swaps',
      'Official Issuances: linked supplements and lifecycle archiving; Certificate of Appearance signature handling and consistent pre-/post-nominal name formatting improved across formal outputs',
      'Dyna AI Assistant release candidate: Sanctum authentication, stored conversations, bounded Bedrock tool-use orchestration, and read-only institutional headcount and leave-trend tools',
    ]],
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
      'ECS native blue/green deployment replaces rolling deploy; migrations now run as a pre-deploy one-off task and a dedicated worker service prevents scheduled or queued jobs from double-firing during blue/green overlap',
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
      'AWS infrastructure security hardening: CloudTrail audit logging, ECR scan-on-push, bucket-scoped S3 IAM policy, nginx rate limiting, PHP hardening, and external service credentials moved to AWS Secrets Manager',
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
  <ul style="margin-left:16px; margin-bottom:10px; font-size:8pt; line-height:1.35;">
    @foreach($items as $item)
    <li>{{ $item }}</li>
    @endforeach
  </ul>
  @endforeach
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- 11. GLOSSARY                                                            --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="page-break">
  <div class="section-label">Section 11</div>
  <h1>Glossary</h1>
  <table>
    <tr><th style="width:22%">Term</th><th>Meaning in Atlas</th></tr>
    @foreach ([
      ['ACIDAA', 'Assistant Chief of the Curriculum and Instruction Division for Academic Affairs.'],
      ['ADOT', 'AWS Distro for OpenTelemetry, used to collect and forward distributed traces.'],
      ['ALP', 'Alternative Learning Program; the Atlas module for accredited student organizations and program governance.'],
      ['Approval Inbox', 'The shared executive queue for authorized, sequence-aware and PIN-confirmed actions originating from multiple modules.'],
      ['AtlasGo', 'The mobile application and API surface used by students and parents for selected Atlas services.'],
      ['Atlas Sentinel', 'The endpoint fleet, health, remediation, remote-help, backup, and biometric-bridge subsystem.'],
      ['CID', 'Curriculum and Instruction Division.'],
      ['CSM', 'Client Satisfaction Measurement used for service feedback and ARTA reporting.'],
      ['DTR', 'Daily Time Record, incorporating biometric, online punch, leave, WFH, travel and gate-pass facts.'],
      ['Dyna', 'The permission-gated Atlas conversational assistant and bounded institutional-data tool orchestration layer.'],
      ['ECS', 'Amazon Elastic Container Service, used with Fargate for container orchestration.'],
      ['FAD', 'Finance and Administrative Division.'],
      ['ILA / ILP', 'Independent Learning Activity / Independent Learning Period.'],
      ['OCD', 'Office of the Campus Director.'],
      ['PDS', 'CSC Personal Data Sheet, including the Work Experience Sheet.'],
      ['PIN signing', 'Point-of-action verification of an authorized signatory before a protected approval or digital signature is applied.'],
      ['RBAC', 'Role-based access control implemented through users, roles, permissions, route middleware and policy checks.'],
      ['SLA', 'Service-level target measured in business days for document-request processing.'],
      ['WAT', 'Weekly Assessment Tracker for plotting, reviewing and monitoring assessment load and compliance.'],
      ['WFH', 'Work From Home attendance and accomplishment reporting.'],
    ] as [$term, $meaning])
    <tr><td><strong>{{ $term }}</strong></td><td>{{ $meaning }}</td></tr>
    @endforeach
  </table>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- 12. AI USAGE STATEMENT                                                  --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="page-break">
  <div class="section-label">Section 12</div>
  <h1>AI Usage Statement</h1>

  <p>This section discloses the role of AI-based tools in building the Atlas platform and in producing this technical documentation, consistent with the Statement of Authorship and Originality recorded in the Copyright and Registration Record.</p>

  <h2>In System Development</h2>
  <p>Atlas is designed, implemented, and maintained by Junlou Tordos and Michael Francisco of the PSHS-CRC MIS team, who hold full authorship and editorial control over the platform's architecture, features, and code. AI-based development assistants — principally Anthropic's Claude and Claude Code — were used throughout implementation as coding, debugging, and code-review tools operating under direct human instruction and review. Every change was directed, evaluated, and accepted or rejected by the named developers before being committed to the codebase; no code was merged or deployed without human review.</p>

  <h2>In This Documentation</h2>
  <p>This technical documentation is generated by the <code>docs:generate</code> artisan command directly from the live, implemented system — its route table, database schema, and permission catalogue are extracted from the running application and database, not authored or estimated by AI. AI assistance was used to help draft and organize the surrounding narrative content (module summaries, workflow descriptions, and glossary entries) and to identify and correct visual and structural defects across drafts of this document. The selection, structure, verification, and final approval of all content — including the accuracy of every technical claim herein — remain the responsibility of the institutional author, Philippine Science High School - Caraga Region Campus in Butuan City, acting through its named developers.</p>

  <div class="info-box">
    <strong>Scope of disclosure.</strong> No content in this document was generated without reference to the actual implemented system, and no AI tool had autonomous authority to add, remove, or publish content in this deposit copy.
  </div>

  <div class="rights-note" style="margin-top:18px;">
    <strong>End of controlled registration copy.</strong><br>
    Document {{ $document['document_id'] }}, Edition {{ $document['edition'] }}, records the Atlas system snapshot dated {{ $document['snapshot_date'] }}. The SHA-256 fingerprint distributed with the final artifact should be used to confirm deposit-copy integrity.
  </div>
</div>

</body>
</html>
