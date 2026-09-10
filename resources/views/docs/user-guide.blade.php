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
.edition-pill { display: inline-block; background: #1e3a8a; color: #fff; border-radius: 14px; padding: 4px 12px; font-size: 8pt; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 18px; }

/* ── Page elements ── */
.page-break { page-break-before: always; }
h1 { font-size: 16pt; font-weight: bold; color: #1e293b; border-bottom: 2px solid #2563eb; padding-bottom: 6px; margin-bottom: 16px; margin-top: 0; }
h2 { font-size: 11pt; font-weight: bold; color: #334155; margin: 16px 0 8px; }
h3 { font-size: 9.5pt; font-weight: bold; color: #475569; margin: 10px 0 6px; }
p  { margin-bottom: 8px; text-align: justify; }
ol, ul { margin-left: 16px; margin-bottom: 8px; }
li { margin-bottom: 3px; text-align: justify; }

/* ── Section headings ── */
.section-label { font-size: 7pt; font-weight: bold; color: #2563eb; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 4px; }

/* ── Tables ── */
table { width: 100%; border-collapse: collapse; margin-bottom: 14px; font-size: 7.5pt; }
th { background: #f1f5f9; color: #475569; font-weight: bold; text-align: left; padding: 5px 7px; border: 1px solid #e2e8f0; font-size: 7pt; text-transform: uppercase; letter-spacing: 0.5px; }
td { padding: 4px 7px; border: 1px solid #e2e8f0; vertical-align: top; }
tr:nth-child(even) td { background: #f8fafc; }

/* ── Info boxes ── */
.info-box  { background: #f8fafc; border-left: 3px solid #2563eb; padding: 8px 12px; margin: 8px 0; border-radius: 0 4px 4px 0; }
.tip-box   { background: #eff6ff; border-left: 3px solid #2563eb; padding: 8px 12px; margin: 8px 0; border-radius: 0 4px 4px 0; }
.warn-box  { background: #fef9c3; border-left: 3px solid #f59e0b; padding: 8px 12px; margin: 8px 0; }
code { font-family: DejaVu Sans Mono, Courier New, monospace; font-size: 7.5pt; background: #f1f5f9; padding: 1px 4px; border-radius: 3px; }

/* ── Module / task cards ── */
.module-card { border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 12px; overflow: hidden; page-break-inside: avoid; }
.module-header { background: #f8fafc; padding: 8px 12px; border-bottom: 1px solid #e2e8f0; }
.module-body   { padding: 8px 12px; }
.module-name   { font-weight: bold; color: #1e293b; font-size: 9.5pt; }
.tag { display: inline-block; background: #dbeafe; color: #1e40af; padding: 1px 6px; border-radius: 10px; font-size: 6.5pt; font-weight: bold; margin: 1px; }
.task-title { font-weight: bold; color: #1e3a8a; font-size: 8.5pt; margin: 8px 0 3px; }

/* ── Workflow illustrations ── */
.diagram { border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px; margin: 10px 0 16px; background: #f8fafc; page-break-inside: avoid; }
.diagram-row { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 0; }
.diagram-row td { border: 1px solid #bfdbfe; background: #fff !important; border-radius: 5px; padding: 10px 8px; text-align: center; vertical-align: middle; font-size: 8pt; }
.diagram-row .arrow { border: none; background: transparent !important; color: #2563eb; width: 4%; font-size: 14pt; font-weight: bold; padding: 0; }
.diagram-title { color: #1e3a8a; font-weight: bold; font-size: 9pt; margin-bottom: 3px; }
.diagram-note { color: #64748b; font-size: 7pt; }
.keep-together { page-break-inside: avoid; }

/* ── Role index ── */
.role-row td { vertical-align: top; }

/* ── TOC ── */
.toc-entry { display: table; width: 100%; padding: 4px 0; border-bottom: 1px dotted #e2e8f0; font-size: 9pt; }
.toc-num   { display: table-cell; width: 28px; color: #2563eb; font-weight: bold; }
.toc-title { display: table-cell; }
</style>
</head>
<body>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- COVER PAGE                                                              --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="cover">
  <div class="cover-logo">Philippine Science High School — Caraga Region Campus in Butuan City</div>
  <div class="edition-pill">User Guide</div>
  <div class="cover-title">Atlas</div>
  <div class="cover-sub">Comprehensive User Guide for All Roles · Edition {{ $document['edition'] }}</div>

  <div class="cover-stats">
    <div class="cover-stat">
      <div class="stat-num">70</div>
      <div class="stat-lbl">Modules Covered</div>
    </div>
    <div class="cover-stat">
      <div class="stat-num">15+</div>
      <div class="stat-lbl">Roles Indexed</div>
    </div>
    <div class="cover-stat">
      <div class="stat-num">6</div>
      <div class="stat-lbl">Module Groups</div>
    </div>
  </div>

  <div class="cover-meta">
    Document ID {{ $document['document_id'] }}<br>
    Edition {{ $document['edition'] }} · Platform Version {{ $document['software_version'] }}<br>
    Generated: {{ $generated_at }}
  </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- TABLE OF CONTENTS                                                       --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="page-break">
  <h1>Table of Contents</h1>
  @foreach ([
    ['', 'About This Guide &amp; Role Quick-Start Index'],
    ['1', 'Getting Started (login, navigation, common screen patterns)'],
    ['2', 'Module Reference (70 modules in 6 groups)'],
    ['3', 'Common Workflows at a Glance'],
    ['4', 'Frequently Asked Questions'],
    ['5', 'Glossary'],
    ['6', 'AI Usage Statement'],
  ] as [$num, $title])
  <div class="toc-entry">
    <div class="toc-num">{{ $num }}</div>
    <div class="toc-title">{!! $title !!}</div>
  </div>
  @endforeach
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- ABOUT THIS GUIDE                                                        --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="page-break">
  <div class="section-label">Introduction</div>
  <h1>About This Guide</h1>

  <p>This guide explains how to use Atlas, the unified campus management platform of the Philippine Science High School - Caraga Region Campus in Butuan City (PSHS-CRC). It is written for every person who uses the system — administrators, HR and finance staff, division chiefs, faculty, non-teaching staff, registrar and student-services staff, students, and parents — not for developers. Where the Technical Documentation describes how Atlas is built, this guide describes how to actually use it: where to click, what each screen means, and how to complete common tasks.</p>

  <div class="info-box">
    <strong>How this guide is organized.</strong> Section 1 covers things every user needs regardless of role — logging in, navigating the sidebar, notifications, and common screen patterns you will see across many modules. Section 2 is the module reference, grouped the same way Atlas groups them in its own sidebar, with a card for every module: what it's for, who uses it, and step-by-step instructions for its most common tasks. Use the <strong>Role Quick-Start Index</strong> below to jump straight to what matters for your role.
  </div>

  <div class="tip-box">
    <strong>Don't have access to something described here?</strong> Atlas uses role-based permissions — you will only see menu items and buttons your account is authorized for. If a task in this guide isn't available to you and you believe it should be, contact your HR Office or the MIS/ICT unit to review your account's roles and permissions.
  </div>

  <h2>Role Quick-Start Index</h2>
  <p style="margin-bottom:6px;">Find your role below for a shortlist of the modules you'll use most. This isn't exhaustive — every role can also use the shared modules in <strong>Getting Started</strong> and most of <strong>Platform &amp; Atlas Tools</strong> (Chat, Notifications, Personal Dashboard, Profile).</p>
  <table>
    <tr><th style="width:20%">Role</th><th>Modules You'll Use Most</th></tr>
    <tr><td><strong>Administrator</strong></td><td>Every module. Administrator bypasses all permission checks and can access anything in this guide.</td></tr>
    <tr><td><strong>Division Chief</strong></td><td>Leave Applications (approval), IPCR/PMS, Facility/Vehicle Requests (approval), Executive Dashboard, Approval Inbox, Org Structure &amp; Data Mgmt</td></tr>
    <tr><td><strong>HR Officer</strong></td><td>Leave Applications, Leave Credits, DTR/Attendance, HR Service Records, HR Document Requests, Payroll, PDS, Recruitment, Learning &amp; Development, Rewards &amp; Recognition, Gate Pass</td></tr>
    <tr><td><strong>Payroll/Finance staff (Budget, Accountant, Bookkeeper, Cashier, Procurement, Property)</strong></td><td>Payroll, PPMP, Procurement, Supply &amp; Property</td></tr>
    <tr><td><strong>Faculty</strong></td><td>Faculty Loading (own schedule), Class Records, Weekly Assessment Tracker, IPCR/PMS, Leave Applications, DTR/Attendance, WFH Attendance, Online Time Punch, Teacher Attendance, Committees &amp; Task Board</td></tr>
    <tr><td><strong>CID Chief / AUH-level academic staff</strong></td><td>Faculty Loading, Schedule Analytics &amp; Governance, Class Records, Weekly Assessment Tracker, IPCR/PMS (faculty chain), Academic Calendar</td></tr>
    <tr><td><strong>Registrar staff</strong></td><td>Student Information System, Student Gate Attendance, Student Clearance, Homeroom Advisory Attendance (Admission Slips)</td></tr>
    <tr><td><strong>Staff (general/administrative)</strong></td><td>Leave Applications, DTR/Attendance, Requests (IT/Vehicle/Facility/Service/Messengerial), Document Tracking, Chat</td></tr>
    <tr><td><strong>GSU / General Services staff</strong></td><td>General Services / Work Requests, Facility Requests, Lost &amp; Found</td></tr>
    <tr><td><strong>MIS / ICT staff</strong></td><td>IT Job Requests, ICT Preventive Maintenance, Atlas Sentinel, Computer Lab Management, Atlas Module Monitor, Atlas WatchTower, Users/Roles &amp; Permissions</td></tr>
    <tr><td><strong>Guidance / Health / Library staff</strong></td><td>Guidance &amp; EGCU, Health Services, Library</td></tr>
    <tr><td><strong>OCD / Office of the Campus Director</strong></td><td>Approval Inbox (PIN signing), Executive Dashboard, Official Issuances, Certificate of Appearance, Announcements</td></tr>
    <tr><td><strong>Student Discipline / Security</strong></td><td>Student Discipline, Student Gate Attendance, Lost &amp; Found</td></tr>
    <tr><td><strong>Student</strong></td><td>Student Portal &amp; AtlasGo (grades, schedule, clearance, RH applications), Live Quiz (as participant), Student Gate Attendance (self-scan where enabled)</td></tr>
    <tr><td><strong>Parent</strong></td><td>Student Portal (linked child's records), gate-attendance SMS notifications (no login required for the SMS itself)</td></tr>
  </table>
</div>
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- SECTION 1 — GETTING STARTED                                             --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="page-break">
  <div class="section-label">Section 1</div>
  <h1>Getting Started</h1>

  <h2>Logging In</h2>
  <p>Atlas uses Google Sign-In only — there is no separate Atlas password to remember. Go to the Atlas login page and choose <strong>Continue with your PSHS-CRC Google Account</strong>. Only official <code>@crc.pshs.edu.ph</code> accounts are authorized; personal Gmail addresses will be rejected. If this is your first time signing in and your digital signature has not been set up yet, you will be prompted once to create a signature and PIN — this is used later for approvals and signed documents, so it's worth completing right away rather than skipping it.</p>
  <div class="tip-box"><strong>Can't sign in?</strong> Confirm you're using your official PSHS-CRC Google account, not a personal one. If the account genuinely should have access and still can't sign in, contact HR or MIS — your account may need to be created or your email corrected in the system first.</div>

  <h2>Finding Your Way Around</h2>
  <p>After signing in you land on your <strong>Personal Dashboard</strong> — a landing page showing your own pending requests, upcoming schedule items, campus announcements, and shortcuts to the modules you use most. The main navigation is the sidebar on the left; it only lists the modules your account's roles and permissions allow, so two people signed in at the same time may see different menus. If a module described in this guide isn't in your sidebar, it isn't part of your role.</p>
  <p>Across the top of most pages you'll find a notification bell (in-app alerts plus browser push notifications, if enabled), a chat icon (real-time messaging with colleagues), and your profile menu (photo, digital signature management, and sign-out).</p>

  <h2>Common Screen Patterns</h2>
  <p>Most Atlas modules share the same handful of UI patterns. Learning them once here saves re-explaining them in every module section below.</p>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Lists and Tables</span></div>
    <div class="module-body">
      <p>Most modules open on a table of records (leave applications, requests, class records, and so on). Tables are paginated — use the page controls at the bottom to move through results — and most have a search box and filter dropdowns above the table to narrow results by status, date, or category.</p>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Creating and Editing Records</span></div>
    <div class="module-body">
      <p>A <strong>+ New / Create</strong> button (usually top-right of the table) opens a form, either as a modal pop-up or a dedicated page. Required fields are marked; submitting an incomplete form will highlight what's missing rather than losing your other entries. Editing an existing record works the same way — look for an edit icon or "Edit" action on the row.</p>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Approvals and the Approval Inbox</span></div>
    <div class="module-body">
      <p>Many requests (leave, document requests, vehicle/facility requests, ICT PMS sign-offs, and others) go through one or more approval stages. If you are an approver, items awaiting your action typically appear both inside that module <em>and</em> in the shared <strong>Approval Inbox</strong>, which brings together everything across modules that's waiting on you — so you don't have to check each module separately. Approving or rejecting usually asks you to confirm with your <strong>digital signature PIN</strong> (the one you set up at first login) rather than a plain button click, since that action is recorded as a formal signed approval.</p>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Uploading Photos and Files</span></div>
    <div class="module-body">
      <p>Wherever Atlas asks for a photo (WFH time-in/out, Online Time Punch, profile picture, attachments), use your device's camera or file picker as prompted — the app handles the upload for you. Files are stored securely and are not publicly accessible; you'll only ever reach them again through the same Atlas screen that uploaded them.</p>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">PDFs, QR Codes, and Printed Documents</span></div>
    <div class="module-body">
      <p>Many official outputs — leave forms, IDs, certificates, class record printouts, issuances — are generated as PDFs you can download or print. Many also carry a QR code; scanning it opens a public verification page confirming the document is genuine and showing its key details, without requiring the viewer to log in.</p>
    </div>
  </div>

  <h2>Getting Help</h2>
  <p>If something in Atlas isn't working as expected, use the in-app <strong>Error Reports</strong> feature (usually reachable from your profile menu or a "Report a problem" link) to describe the issue — it automatically attaches a screenshot and gives you a reference number (format <code>ERR-YYYY-NNNN</code>) you can quote when following up with MIS.</p>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- SECTION 2 — MODULE REFERENCE                                            --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="page-break">
  <div class="section-label">Section 2</div>
  <h1>Module Reference</h1>
  <p>Modules are grouped below the same way they're grouped in Atlas's own sidebar. Role tags on each module show who typically uses it — your account may show or hide specific buttons within a module depending on your exact permissions.</p>

  <h2 style="margin-top:18px; border-bottom:1px solid #e2e8f0; padding-bottom:4px;">Human Resources &amp; Payroll</h2>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Leave Applications</span> <span class="tag">All Employees</span> <span class="tag">Division Chief</span> <span class="tag">HR Officer</span></div>
    <div class="module-body">
      <p>File and track CSC-compliant leave applications. Approval goes through your Division Chief, then HR. Once approved, your leave is automatically reflected on your DTR — you don't need to separately request a DTR adjustment.</p>
      <div class="task-title">Filing a leave application</div>
      <ol>
        <li>Go to <strong>Leave Applications</strong> and click <strong>New Application</strong>.</li>
        <li>Select the leave type (e.g. Vacation, Sick, Special Privilege), the date range, and state your reason if required for that type.</li>
        <li>Submit. Your application is sent to your Division Chief for the first approval stage.</li>
        <li>Track status on the same page — you'll see it move from Pending → Forwarded → Approved (or Rejected, with a reason).</li>
      </ol>
      <div class="task-title">Approving leave (Division Chief / HR Officer)</div>
      <ol>
        <li>Open the application from your <strong>Approval Inbox</strong> or the Leave Applications list.</li>
        <li>Review the requested dates and leave-credit balance shown.</li>
        <li>Confirm with your signature PIN to approve, or reject with a reason.</li>
      </ol>
      <div class="tip-box">Leave computation only counts Monday–Friday — weekends are automatically excluded from the day count.</div>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Leave Credits</span> <span class="tag">All Employees</span> <span class="tag">HR Officer</span></div>
    <div class="module-body">
      <p>View your Vacation Leave (VL) and Sick Leave (SL) balances, which accrue monthly. Teaching staff generally don't accrue VL/SL the same way non-teaching staff do (their leave is governed by the academic calendar instead), except designated SSD/CID chief positions.</p>
      <div class="task-title">Checking your balance</div>
      <ol>
        <li>Go to <strong>Leave Credits</strong> to see your current ledger — each accrual, deduction, and adjustment is listed with a date.</li>
      </ol>
      <div class="task-title">Adjusting an employee's credits (HR Officer)</div>
      <ol>
        <li>Open the employee's ledger from the HR-facing Leave Credits screen.</li>
        <li>Use the adjustment action to add a manual correction, with a reason — this keeps the ledger auditable rather than silently overwriting a balance.</li>
      </ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">DTR / Attendance</span> <span class="tag">All Employees</span> <span class="tag">HR Officer</span></div>
    <div class="module-body">
      <p>Your Daily Time Record, combining biometric terminal punches, Online Time Punch, WFH, approved leave, and gate pass deductions into one monthly record used for payroll.</p>
      <div class="task-title">Viewing your DTR</div>
      <ol>
        <li>Go to <strong>DTR / Attendance</strong> and select the month.</li>
        <li>Each day shows your recorded time-in/out and any flags (late, undertime, on-leave, travel).</li>
      </ol>
      <div class="task-title">COS employees: advance cut-off entry</div>
      <ol>
        <li>If you're a Contract-of-Service employee, use the advance-entry option to self-generate your entries ahead of a payroll cut-off, rather than waiting for the automatic monthly generation.</li>
      </ol>
      <div class="tip-box">If a specific day looks wrong, check whether a leave, WFH, or gate-pass record needs to be filed/approved first — DTR is generated from those, not edited freely by employees.</div>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">WFH Attendance</span> <span class="tag">All Employees</span> <span class="tag">HR Officer</span></div>
    <div class="module-body">
      <p>Work-From-Home time-in/out with photo verification, plus an accomplishment log for what you worked on that day.</p>
      <div class="task-title">Recording WFH time-in / time-out</div>
      <ol>
        <li>Go to <strong>WFH Attendance</strong> and select <strong>Time In</strong>.</li>
        <li>Allow camera access when prompted and capture your photo.</li>
        <li>Repeat with <strong>Time Out</strong> at the end of your work session.</li>
        <li>Add your accomplishments for the day in the accomplishment log.</li>
      </ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Online Time Punch</span> <span class="tag">All Employees</span> <span class="tag">HR Officer</span></div>
    <div class="module-body">
      <p>An on-campus alternative to biometric terminals — you punch in/out yourself using facial verification, with location checks to confirm you're on the authorized network/campus zone.</p>
      <div class="task-title">First-time setup: face enrollment</div>
      <ol>
        <li>Go to <strong>Online Time Punch → Face Enrollment</strong> and follow the prompts to capture your facial reference.</li>
        <li>Wait for HR to review and approve your enrollment before your first punch.</li>
      </ol>
      <div class="task-title">Punching in/out</div>
      <ol>
        <li>Go to <strong>Online Time Punch</strong>, confirm you're within an approved location/network zone, and select Time In or Time Out.</li>
      </ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Gate Pass</span> <span class="tag">All Employees</span> <span class="tag">Division Chief</span> <span class="tag">OCD</span></div>
    <div class="module-body">
      <p>Request permission to leave campus during working hours. Approved gate passes automatically reduce your DTR time rather than counting as absence — and this stays correct even if DTR is later regenerated.</p>
      <div class="task-title">Requesting a gate pass</div>
      <ol>
        <li>Go to <strong>Gate Pass</strong> and click <strong>New Request</strong>, giving your reason and expected time out/return.</li>
        <li>Submit for Division Chief approval (OCD division staff skip this stage).</li>
        <li>Print the approved pass if a physical copy is needed at the gate.</li>
      </ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Employee Digital ID</span> <span class="tag">All Employees</span></div>
    <div class="module-body">
      <p>A wallet-style digital ID with a QR code that anyone can scan to verify your employment status live, without needing an Atlas login.</p>
      <div class="task-title">Viewing / sharing your digital ID</div>
      <ol>
        <li>Go to <strong>Employee Digital ID</strong> to view your ID card and QR code.</li>
        <li>If it's ever lost or compromised, ask HR to revoke and reissue it.</li>
      </ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">HR Service Records</span> <span class="tag">All Employees</span> <span class="tag">HR Officer</span></div>
    <div class="module-body">
      <p>Your official, chronological service history — appointments, positions, salary changes, and leave-without-pay periods — each entry backed by evidence and formally verified by HR.</p>
      <div class="task-title">Viewing your own record</div>
      <ol><li>Go to <strong>HR Service Records</strong> to see your verified service history.</li></ol>
      <div class="task-title">HR: adding/verifying an entry</div>
      <ol>
        <li>Add a new service entry with supporting documentation attached.</li>
        <li>Verify the entry once evidence is confirmed sufficient — corrections later create a new, superseding entry rather than silently editing history.</li>
      </ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">HR Document Requests</span> <span class="tag">All Employees</span> <span class="tag">HR Officer</span> <span class="tag">OCD</span></div>
    <div class="module-body">
      <p>Request official certifications — Certificate of Employment, compensation, service record, leave credits, last salary, government service, or a custom certification.</p>
      <div class="task-title">Requesting a certificate</div>
      <ol>
        <li>Go to <strong>HR Document Requests → New Request</strong> and choose the certificate type.</li>
        <li>State your purpose and preferred delivery/pickup method.</li>
        <li>Track status — some certificate types are HR-issued directly, while others requiring the Campus Director's signature route through the Approval Inbox first.</li>
        <li>Once issued, download the PDF (with QR verification) or collect it per the chosen delivery method.</li>
      </ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">HR Dashboard</span> <span class="tag">HR Officer</span></div>
    <div class="module-body">
      <p>A cross-module view for HR — today's leave and gate-pass activity plus analytics spanning HR, Recruitment, PMS, Learning &amp; Development, SALN, and Rewards.</p>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Payroll</span> <span class="tag">Payroll Officer</span> <span class="tag">Cashier</span></div>
    <div class="module-body">
      <p>Runs payroll from the Salary Standardization Law schedule, computing statutory deductions (GSIS, PhilHealth, Pag-IBIG, tax), and generates payslips and cashier disbursement batches.</p>
      <div class="task-title">Processing a payroll run</div>
      <ol>
        <li>Start a new payroll run for the target period.</li>
        <li>Review computed salaries and deductions per employee.</li>
        <li>Finalize the run to generate payslip PDFs.</li>
        <li>Cashier: process disbursement using the per-batch items and the combined PDF for the batch.</li>
      </ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">PDS</span> <span class="tag">All Employees</span></div>
    <div class="module-body">
      <p>Your CSC Personal Data Sheet (Form 212), including the Work Experience Sheet.</p>
      <div class="task-title">Updating your PDS</div>
      <ol>
        <li>Go to <strong>PDS</strong> and update the relevant tab (Personal Info, Family, Education, Civil Service Eligibility, Work Experience, etc.).</li>
        <li>Use the trainings CSV import if you have a bulk list to add, rather than entering each one manually.</li>
        <li>Export to Excel when you need the official CSC-formatted copy.</li>
      </ol>
      <div class="warn-box">Saving your Work Experience Sheet replaces the existing trainings list with what you submit — double-check the full list before saving rather than assuming it merges.</div>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">SALN</span> <span class="tag">All Employees</span> <span class="tag">HR Officer</span></div>
    <div class="module-body">
      <p>Your annual Statement of Assets, Liabilities, and Net Worth, as required by CSC.</p>
      <div class="task-title">Filing your SALN</div>
      <ol>
        <li>Go to <strong>SALN</strong> and complete the annual submission form.</li>
        <li>Submit for HR review.</li>
        <li>Download the finalized PDF once reviewed.</li>
      </ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Recruitment</span> <span class="tag">HR Officer</span> <span class="tag">Recruitment Officer</span> <span class="tag">HRMPSB</span></div>
    <div class="module-body">
      <p>End-to-end hiring: job postings, the public applicant portal, shortlisting, interviews, and placement.</p>
      <div class="task-title">Publishing a job posting</div>
      <ol>
        <li>Create a job posting, attaching the relevant plantilla item number(s).</li>
        <li>Publish — it becomes visible on the public <strong>/jobs</strong> portal for applicants.</li>
        <li>Review incoming applications, shortlist, and schedule interviews.</li>
        <li>Record placement once a candidate is selected.</li>
      </ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Learning &amp; Development</span> <span class="tag">All Employees</span> <span class="tag">HR Officer</span></div>
    <div class="module-body">
      <p>Training Needs Assessment, learning programs/sessions, and Individual Development Plans (IDPs).</p>
      <div class="task-title">Enrolling in / logging a training</div>
      <ol>
        <li>Browse available learning programs and sessions.</li>
        <li>Register your participation; HR records attendance and evaluation afterward.</li>
      </ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Rewards &amp; Recognition</span> <span class="tag">All Employees</span> <span class="tag">HR Officer</span></div>
    <div class="module-body">
      <p>Nominate colleagues for recognition; nominations go through evaluation and approval before being recorded.</p>
      <div class="task-title">Submitting a nomination</div>
      <ol>
        <li>Go to <strong>Rewards &amp; Recognition</strong> and select the reward type.</li>
        <li>Nominate a colleague with supporting justification.</li>
      </ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Travel</span> <span class="tag">All Employees</span> <span class="tag">Division Chief</span> <span class="tag">OCD</span></div>
    <div class="module-body">
      <p>Travel authority requests, reviewed by your Division Chief, OCD, and the Finance and Administrative Division.</p>
      <div class="task-title">Requesting travel authority</div>
      <ol>
        <li>Go to <strong>Travel → New Request</strong> and provide your itinerary and purpose.</li>
        <li>Submit for approval through the division/OCD/FAD stages.</li>
      </ol>
    </div>
  </div>

</div>

<div class="page-break">
  <h2 style="margin-top:0; border-bottom:1px solid #e2e8f0; padding-bottom:4px;">Performance Management</h2>

  <div class="module-card">
    <div class="module-header"><span class="module-name">IPCR / PMS</span> <span class="tag">All Employees</span> <span class="tag">Division Chief</span> <span class="tag">PMT</span></div>
    <div class="module-body">
      <p>Your Individual Performance Commitment and Review, aligned to CSC's Strategic Performance Management System. Staff route through Division Chief → PMT → Director; faculty route through AUH → ACIDAA → CID Chief.</p>
      <div class="task-title">Submitting your IPCR</div>
      <ol>
        <li>Go to <strong>IPCR / PMS</strong> for the current rating period and fill in your commitments/targets at the start of the period.</li>
        <li>At the end of the period, record your accomplishments against each target.</li>
        <li>Submit for rating — once submitted, the form becomes locked/immutable, so review carefully before submitting.</li>
      </ol>
      <div class="task-title">Rating a subordinate (Division Chief / PMT)</div>
      <ol>
        <li>Open the submitted IPCR from your approval queue.</li>
        <li>Enter ratings against each commitment/target and any remarks.</li>
        <li>Submit your rating to move it to the next stage in the chain.</li>
      </ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Committees &amp; Task Board</span> <span class="tag">Committee Members</span> <span class="tag">Committee Chair</span></div>
    <div class="module-body">
      <p>A shared committees structure used across Data Management, Faculty Loading, and PMS, with a Monday.com-style task board and per-rating-period member ratings by the chair.</p>
      <div class="task-title">Using the task board</div>
      <ol>
        <li>Open your committee's task board and move tasks across status columns as work progresses.</li>
        <li>Committee chair: rate members at the end of each rating period.</li>
      </ol>
    </div>
  </div>
</div>

<div class="page-break">
  <h2 style="margin-top:0; border-bottom:1px solid #e2e8f0; padding-bottom:4px;">Curriculum &amp; Instruction</h2>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Faculty Loading</span> <span class="tag">Faculty</span> <span class="tag">CID Chief</span> <span class="tag">AUH</span></div>
    <div class="module-body">
      <p>Manages teaching schedules and workload for the school year — automatically placed without conflicts, with manual drag-and-drop adjustment when needed.</p>
      <div class="task-title">Viewing your schedule (Faculty)</div>
      <ol>
        <li>Go to <strong>Faculty Loading → My Load</strong> to see your assigned subjects, sections, and time slots for the current school year.</li>
      </ol>
      <div class="task-title">Building/adjusting schedules (CID Chief / AUH)</div>
      <ol>
        <li>Use <strong>Auto-Schedule</strong> to generate a conflict-free schedule automatically for the school year, or drag-and-drop individual assignments to adjust manually.</li>
        <li>Live conflict detection warns you immediately if a change creates a double-booking.</li>
        <li>Submit the finished schedule for the approval workflow described in <strong>Schedule Analytics &amp; Governance</strong> below.</li>
      </ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Schedule Analytics &amp; Governance</span> <span class="tag">CID Chief</span> <span class="tag">AUH</span></div>
    <div class="module-body">
      <p>Load-balance analytics plus controlled schedule approvals, amendments, and swap requests once a schedule has been built in Faculty Loading.</p>
      <div class="task-title">Approving a schedule</div>
      <ol>
        <li>Review the submitted schedule batch and the load-balance analytics shown.</li>
        <li>Approve, or return it with remarks for revision.</li>
      </ol>
      <div class="task-title">Requesting a swap</div>
      <ol>
        <li>Faculty can submit a schedule swap request for review; approved swaps update both parties' schedules together.</li>
      </ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Class Records</span> <span class="tag">Faculty</span> <span class="tag">CID Chief</span></div>
    <div class="module-body">
      <p>Quarterly assessment and grade recording for every subject you teach, including attendance, uniform compliance, and clinic-verified excused cutting.</p>
      <div class="task-title">Recording an assessment and grades</div>
      <ol>
        <li>Go to <strong>Class Records</strong>, select the subject/section and quarter.</li>
        <li>Add an assessment (quiz, activity, exam) with its category and maximum score.</li>
        <li>Enter each student's score; the running quarterly grade updates automatically.</li>
        <li>Record daily attendance and uniform remarks in the same quarter view.</li>
      </ol>
      <div class="warn-box">Once a school year is locked (typically after final grades are submitted), class records for that year become read-only. Make sure grades are finalized before the lock.</div>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Weekly Assessment Tracker</span> <span class="tag">Faculty</span> <span class="tag">CID Chief</span></div>
    <div class="module-body">
      <p>A calendar for plotting when assessments will happen, so CID can monitor assessment load across all subjects and avoid overloading students in a single week.</p>
      <div class="task-title">Plotting an assessment</div>
      <ol>
        <li>Go to <strong>Weekly Assessment Tracker</strong> and click a date on the calendar to plot a planned assessment.</li>
        <li>CID reviews the compliance dashboard to spot overloaded weeks across subjects.</li>
      </ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Alternative Learning Program</span> <span class="tag">Faculty Advisers</span> <span class="tag">Registrar</span> <span class="tag">OCD</span></div>
    <div class="module-body">
      <p>Accreditation and governance for student organizations and alternative learning programs — membership, activities, finances, and compliance reporting for each annual cycle.</p>
      <div class="task-title">Running an annual cycle</div>
      <ol>
        <li>Open or create the program's annual cycle, register members/officers, and log activities and attendance as they happen through the year.</li>
        <li>Record financial entries and documentary requirements as they come in.</li>
        <li>Submit for adviser/coordinator/Registrar/OCD review to close the cycle.</li>
      </ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Homeroom Advisory Attendance</span> <span class="tag">Advisers</span> <span class="tag">Registrar</span> <span class="tag">Homeroom Coordinator</span></div>
    <div class="module-body">
      <p>Whole-day advisory attendance — daily records, school activities, Flag Ceremony/Retreat, and monthly Record on Attendance and Punctuality reporting.</p>
      <div class="task-title">Logging daily attendance (Adviser)</div>
      <ol>
        <li>Go to <strong>Homeroom Advisory Attendance</strong> for your section and mark each student present/absent/tardy for the day.</li>
        <li>At month-end, generate the monthly report for Coordinator review.</li>
      </ol>
      <div class="task-title">Issuing a Class Admission Slip (Registrar)</div>
      <ol>
        <li>When a student returns after an absence needing formal clearance, issue an Admission Slip from this module so the adviser can admit them back to class.</li>
      </ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Academic Calendar</span> <span class="tag">CID</span></div>
    <div class="module-body">
      <p>The authoritative calendar of holidays, suspensions, and institutional activities, consumed automatically by Homeroom Attendance and schedule-adjustment features.</p>
      <div class="task-title">Adding a calendar entry</div>
      <ol><li>Go to <strong>Academic Calendar</strong> and add the date range and type (holiday, suspension, activity) — downstream attendance and scheduling features pick this up automatically.</li></ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Teacher Attendance</span> <span class="tag">Faculty</span></div>
    <div class="module-body">
      <p>Classroom-level presence tracking using printable NFC classroom cards, with a QR fallback when NFC isn't available.</p>
      <div class="task-title">Tapping in to a classroom</div>
      <ol><li>Tap your ID against the classroom's NFC card, or use the QR fallback if you're on the approved campus network.</li></ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Live Quiz</span> <span class="tag">Faculty</span> <span class="tag">Student</span></div>
    <div class="module-body">
      <p>Kahoot-style live quizzes for classroom use, with a join code, streaks, and a post-session report.</p>
      <div class="task-title">Hosting a quiz (Faculty)</div>
      <ol>
        <li>Create a quiz session and share the join code with your class.</li>
        <li>Start the session once everyone has joined; reveal answers after each question.</li>
      </ol>
      <div class="task-title">Joining a quiz (Student)</div>
      <ol><li>Enter the join code your teacher shares to join the live session.</li></ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Science Lab Management</span> <span class="tag">Faculty</span> <span class="tag">Science Research Assistant</span></div>
    <div class="module-body">
      <p>Laboratory, equipment, and reagent management — requests, reservations, calibration schedules, and safety reporting.</p>
      <div class="task-title">Requesting equipment/reagents</div>
      <ol>
        <li>Submit a request specifying what's needed and when.</li>
        <li>Track endorsement and approval status on the same request.</li>
      </ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Computer Lab Management</span> <span class="tag">Faculty</span> <span class="tag">MIS</span></div>
    <div class="module-body">
      <p>Computer lab scheduling and booking with conflict checks and formal printable schedules.</p>
      <div class="task-title">Booking a lab</div>
      <ol>
        <li>Select an available lab and time slot; the system blocks conflicting bookings automatically.</li>
        <li>Submit for approval where required.</li>
      </ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Competitions &amp; Winnings</span> <span class="tag">Faculty</span> <span class="tag">CID</span></div>
    <div class="module-body">
      <p>Record student/employee competition entries, coaches, and awards.</p>
      <div class="task-title">Logging an entry</div>
      <ol><li>Add the competition, participants, coach/co-coach, and any award — an IT Job Request for graphic design (e.g. a certificate or tarpaulin) can be auto-filed at the same time if needed.</li></ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Activity Management</span> <span class="tag">Faculty</span> <span class="tag">CID</span></div>
    <div class="module-body">
      <p>Propose and run school activities — in-house events or external training — with co-proponents, monitoring, and post-activity evaluations.</p>
      <div class="task-title">Proposing an activity</div>
      <ol>
        <li>Submit an activity proposal with its type, co-proponents, and schedule.</li>
        <li>After the activity, generate certificates and collect evaluations.</li>
      </ol>
    </div>
  </div>
</div>

<div class="page-break">
  <h2 style="margin-top:0; border-bottom:1px solid #e2e8f0; padding-bottom:4px;">Registrar &amp; Student Services</h2>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Student Information System</span> <span class="tag">Registrar</span></div>
    <div class="module-body">
      <p>Enrollment, section assignment, promotions, report cards, and transcripts. Students are enrolled first, then assigned to a section.</p>
      <div class="task-title">Enrolling a student</div>
      <ol>
        <li>Go to <strong>Registrar → Add Student</strong> (walk-in) or review a self-service enrollment application.</li>
        <li>Complete the enrollment application, then assign the student to a section — individually or in bulk by grade level.</li>
      </ol>
      <div class="task-title">Printing an ID card</div>
      <ol><li>From the student's profile, crop their photo and print a CR-80 ID card.</li></ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Student Gate Attendance</span> <span class="tag">Gate/Security</span> <span class="tag">Registrar</span></div>
    <div class="module-body">
      <p>Gate attendance using PISAY ID barcodes, with automatic SMS notification to parents and an audited guard directory.</p>
      <div class="task-title">Scanning a student at the gate</div>
      <ol>
        <li>Scan the student's PISAY ID barcode using the kiosk's USB scanner (or the front/rear camera).</li>
        <li>The scan is logged and a notification SMS is sent to the registered parent contact automatically.</li>
      </ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Student Clearance</span> <span class="tag">Advisers</span> <span class="tag">Registrar</span></div>
    <div class="module-body">
      <p>Year-end clearance workflow — advisers review and clear their advisees before final release.</p>
      <div class="task-title">Clearing a student (Adviser)</div>
      <ol><li>Review any outstanding blockers for your advisee and mark them cleared once resolved.</li></ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Guidance &amp; EGCU</span> <span class="tag">Guidance</span></div>
    <div class="module-body">
      <p>Guidance consultations, session reports, referrals, and the EGCU Cumulative Record.</p>
      <div class="task-title">Logging a consultation</div>
      <ol>
        <li>Record the session (kiosk sign-in or manual entry), then complete the session report and any referral needed.</li>
      </ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Health Services</span> <span class="tag">Nurse</span></div>
    <div class="module-body">
      <p>Clinic consultation records, a walk-in kiosk, and physician schedules.</p>
      <div class="task-title">Recording a clinic visit</div>
      <ol><li>Log the consultation from the clinic kiosk or the Health Services screen, noting the reason and any treatment given.</li></ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Library</span> <span class="tag">Librarian</span></div>
    <div class="module-body">
      <p>Collections, categories, borrowing workflow, and an attendance kiosk.</p>
      <div class="task-title">Checking out a book</div>
      <ol><li>Find the item in Collections and process the borrowing transaction; returns are logged the same way.</li></ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Residence Hall</span> <span class="tag">Dorm Manager</span> <span class="tag">Interns</span></div>
    <div class="module-body">
      <p>Dormitory ("Residence Hall") applications, bed assignment, appliances, fees, and leave passes for interns.</p>
      <div class="task-title">Applying for residence</div>
      <ol><li>Submit an application (including via the Student Portal for students); once approved, a bed is assigned on the visual floor map.</li></ol>
      <div class="task-title">Requesting a leave pass</div>
      <ol><li>Interns can request a leave pass for time away from the hall, subject to Dorm Manager approval.</li></ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Student Discipline</span> <span class="tag">Student Discipline Officer</span></div>
    <div class="module-body">
      <p>SDO case management — Anecdotal Reports, an offense catalogue, and confiscated-item tracking.</p>
      <div class="task-title">Filing an anecdotal report</div>
      <ol>
        <li>Create a case, select the relevant offense from the catalogue, and record the incident details.</li>
        <li>Sign the finalized report with your PIN; generate the case PDF as needed.</li>
      </ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Lost &amp; Found</span> <span class="tag">GSU</span></div>
    <div class="module-body">
      <p>A custody trail for found items, with honesty points awarded to whoever turns an item in.</p>
      <div class="task-title">Logging a found item</div>
      <ol><li>Record the item and its finder when GSU receives it — the finder is automatically credited honesty points.</li></ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Student Portal &amp; AtlasGo</span> <span class="tag">Student</span> <span class="tag">Parent</span></div>
    <div class="module-body">
      <p>The student- and parent-facing side of Atlas — grades, schedule, clearance status, and Residence Hall applications — available on the web (Student Portal) and as the AtlasGo mobile app.</p>
      <div class="task-title">Signing in (Student)</div>
      <ol>
        <li>Open the Student Portal (or AtlasGo) and sign in with your official PSHS-CRC Google account.</li>
        <li>The first time you sign in, you'll be asked to link your Google account to your student record using your PISAY ID.</li>
      </ol>
      <div class="task-title">Checking grades and schedule</div>
      <ol><li>From the portal dashboard, open Grades or Schedule to see your current-quarter results and class schedule.</li></ol>
      <div class="info-box"><strong>AtlasGo</strong> mirrors the Student Portal's features as a native mobile app — Android is available as a direct download; check with MIS for the current iOS availability.</div>
    </div>
  </div>
</div>

<div class="page-break">
  <h2 style="margin-top:0; border-bottom:1px solid #e2e8f0; padding-bottom:4px;">Administration, Requests &amp; Documents</h2>

  <div class="module-card">
    <div class="module-header"><span class="module-name">IT Job Requests</span> <span class="tag">All Employees</span> <span class="tag">Division Chief</span> <span class="tag">MIS</span></div>
    <div class="module-body">
      <p>Request ICT assistance — 3-stage approval (Division Chief → OCD → MIS), with a priority queue and CSM feedback afterward.</p>
      <div class="task-title">Filing a request</div>
      <ol>
        <li>Go to <strong>IT Job Requests → New Request</strong>, describe the issue, and submit.</li>
        <li>For Technical Assistance on Events, file at least 3 days in advance — late requests may not be accommodated.</li>
        <li>MIS assesses and assigns priority; you'll be notified as it's actioned.</li>
        <li>Rate the service via CSM feedback once resolved.</li>
      </ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">ICT Preventive Maintenance</span> <span class="tag">MIS</span> <span class="tag">OCD</span></div>
    <div class="module-body">
      <p>Scheduled maintenance programs for ICT equipment, with agent-check history and OCD PIN-signed approval.</p>
      <div class="task-title">Logging a maintenance check</div>
      <ol><li>Record the maintenance activity against the equipment's PMS program; sign off through the Approval Inbox once complete.</li></ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Requests: Vehicle, Facility, Service, Messengerial</span> <span class="tag">All Employees</span> <span class="tag">Division Chief</span> <span class="tag">FAD</span> <span class="tag">GSU</span></div>
    <div class="module-body">
      <p>Four related request types for campus services — vehicles, facility use, general services, and messengerial tasks. Vehicle requests include an extra FAD review stage.</p>
      <div class="task-title">Filing a request</div>
      <ol>
        <li>Choose the relevant request type and submit with your details and preferred date/time.</li>
        <li>Facility and Vehicle requests need at least 3 days' advance filing.</li>
        <li>Track approval stages; printed forms carry a QR verification code.</li>
      </ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">General Services / Work Requests</span> <span class="tag">GSU</span></div>
    <div class="module-body">
      <p>GSU work orders — repairs and general services tasks, with categories, assignment, and a completion workflow.</p>
      <div class="task-title">Processing a work order</div>
      <ol><li>Review incoming work orders, assign to staff, and mark complete when finished — dashboard analytics track overall throughput.</li></ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Document Tracking</span> <span class="tag">Records Staff</span> <span class="tag">OCD</span></div>
    <div class="module-body">
      <p>Tracks internal and external documents as they're routed between offices, using configurable routing templates.</p>
      <div class="task-title">Logging and routing an external document</div>
      <ol>
        <li>Log the document and scan it (stored to Google Drive) upon receipt.</li>
        <li>OCD reviews and routes it to the appropriate office using the applicable routing chain.</li>
      </ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Official Issuances</span> <span class="tag">OCD</span> <span class="tag">Records Staff</span></div>
    <div class="module-body">
      <p>Control-numbered official issuances (memos, orders) with a rich-text editor, PIN signing, and QR-verified delivery.</p>
      <div class="task-title">Creating and issuing a document</div>
      <ol>
        <li>Draft the issuance using the built-in editor and assign it a category.</li>
        <li>Route for PIN signing, then issue — it's queued for PDF generation and email delivery, with full-text search available afterward.</li>
      </ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Knowledge Management</span> <span class="tag">Records Staff</span></div>
    <div class="module-body">
      <p>The OED issuance repository, searchable by full text (including scanned documents via text extraction).</p>
      <div class="task-title">Finding a document</div>
      <ol><li>Search by keyword — matches include content extracted from scanned PDFs, not just titles.</li></ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Announcements</span> <span class="tag">OCD</span> <span class="tag">Administrator</span></div>
    <div class="module-body">
      <p>Campus-wide or targeted announcements with a poster image and automatic bell notifications.</p>
      <div class="task-title">Publishing an announcement</div>
      <ol><li>Create the announcement, upload a poster if applicable, choose the audience, and publish — recipients get a notification automatically.</li></ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Certificate of Appearance</span> <span class="tag">OCD</span></div>
    <div class="module-body">
      <p>Per-visitor Certificate of Appearance PDFs, QR-verified and PIN-signed, emailed directly to external visitors.</p>
      <div class="task-title">Issuing a certificate</div>
      <ol><li>Log the visit, then generate and PIN-sign the certificate — it's emailed to the visitor automatically.</li></ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Approval Inbox</span> <span class="tag">Approvers (all roles)</span></div>
    <div class="module-body">
      <p>Your single queue for everything across Atlas awaiting your approval or PIN signature — see <strong>Getting Started</strong> for how approvals work in general.</p>
      <div class="task-title">Clearing your inbox</div>
      <ol><li>Open each item, review the details, and approve/decline with your signature PIN as prompted.</li></ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">PPMP</span> <span class="tag">Property Officer</span> <span class="tag">Budget Officer</span> <span class="tag">OCD</span></div>
    <div class="module-body">
      <p>Project Procurement Management Plan — unit-level plans roll up to a division consolidation, then formal review, before becoming the basis for procurement.</p>
      <div class="task-title">Submitting a unit PPMP</div>
      <ol>
        <li>Create your unit's PPMP entries against the APP-CSE catalogue.</li>
        <li>Submit for division consolidation, then Property Officer → Budget Officer → Head of Agency review.</li>
        <li>Export to Excel for the consolidated APP once approved.</li>
      </ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Procurement</span> <span class="tag">Procurement Officer</span> <span class="tag">Accountant</span> <span class="tag">Bookkeeper</span> <span class="tag">Cashier</span> <span class="tag">OCD</span></div>
    <div class="module-body">
      <p>The Purchase Request → Obligation Request → Disbursement Voucher workflow, with accountant/bookkeeper/cashier stages and OCD payment signing.</p>
      <div class="task-title">Processing a purchase</div>
      <ol>
        <li>Create a Purchase Request against an approved PPMP line item.</li>
        <li>Progress it through ORS creation, accountant/bookkeeper review, and OCD payment signing.</li>
        <li>Track delivery status once the disbursement voucher is finalized.</li>
      </ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Supply &amp; Property</span> <span class="tag">Supply Officer</span> <span class="tag">Property Officer</span></div>
    <div class="module-body">
      <p>Supply management (IAR, RIS, stock cards) and property management (ICS/PAR issuance, transfers, disposal, RPCI reports).</p>
      <div class="task-title">Issuing supplies</div>
      <ol><li>Receive stock via an Inspection and Acceptance Report (IAR), then issue items against a Requisition and Issue Slip (RIS).</li></ol>
      <div class="task-title">Issuing/transferring property</div>
      <ol><li>Issue new property with an ICS or PAR; use the transfer function to move accountability between employees, and disposal (BSR) to formally retire an item.</li></ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Executive Dashboard</span> <span class="tag">OCD</span> <span class="tag">Division Chief</span></div>
    <div class="module-body">
      <p>Cross-system analytics for OCD and Division Chiefs — attention flags and unit scorecards across the whole platform.</p>
      <div class="task-title">Reviewing your unit's scorecard</div>
      <ol><li>Open the dashboard and use the scope selector to focus on your division/unit's metrics and any flagged attention items.</li></ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Org Structure &amp; Data Mgmt</span> <span class="tag">Administrator</span> <span class="tag">Data Management Staff</span></div>
    <div class="module-body">
      <p>Divisions, offices, and academic units, with head assignments and org exports.</p>
      <div class="task-title">Updating org structure</div>
      <ol><li>Add or edit divisions/offices/units and assign heads; use <strong>Sync to Faculty Loading</strong> to push academic-unit changes where applicable.</li></ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Users, Roles &amp; Permissions</span> <span class="tag">Administrator</span> <span class="tag">HR Officer</span></div>
    <div class="module-body">
      <p>RBAC administration — assigning roles and permission grants. The Administrator role bypasses all permission checks.</p>
      <div class="task-title">Assigning a role</div>
      <ol><li>Open the user's account and add/remove roles — a user can hold more than one role at a time.</li></ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Error Reports</span> <span class="tag">All Employees</span> <span class="tag">MIS</span></div>
    <div class="module-body">
      <p>In-app error reporting with an automatic screenshot and reference number — see <strong>Getting Started → Getting Help</strong>.</p>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">CSM Feedback</span> <span class="tag">Clients/Requesters</span> <span class="tag">Administrator</span></div>
    <div class="module-body">
      <p>The Client Satisfaction Measurement survey (ARTA-compliant) attached to service transactions like IT Job Requests and other request types.</p>
      <div class="task-title">Rating a service</div>
      <ol><li>After a transaction is completed, you'll be prompted to rate it — this feeds the CSM dashboard and export used for reporting.</li></ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">GAD Dashboard</span> <span class="tag">Public</span></div>
    <div class="module-body">
      <p>A public gender-and-development statistics page — no login required.</p>
    </div>
  </div>
</div>

<div class="page-break">
  <h2 style="margin-top:0; border-bottom:1px solid #e2e8f0; padding-bottom:4px;">Platform &amp; Atlas Tools</h2>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Chat</span> <span class="tag">All Employees</span></div>
    <div class="module-body">
      <p>Real-time messaging with colleagues — direct and group conversations, image sharing, and typing indicators.</p>
      <div class="task-title">Starting a conversation</div>
      <ol><li>Open <strong>Chat</strong>, start a new conversation or group, and send messages or images (up to 10 MB).</li></ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Notifications</span> <span class="tag">All Employees</span></div>
    <div class="module-body">
      <p>The in-app bell plus browser push notifications for things that need your attention — approvals waiting, announcements, and more.</p>
      <div class="task-title">Enabling push notifications</div>
      <ol><li>Accept your browser's notification permission prompt when offered, so you receive alerts even when Atlas isn't the active tab.</li></ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Personal Dashboard</span> <span class="tag">All Employees</span></div>
    <div class="module-body">
      <p>Your personalized landing page after signing in — your own requests, schedule, announcements, and shortcuts to modules you use most.</p>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Atlas Sentinel</span> <span class="tag">MIS</span></div>
    <div class="module-body">
      <p>ICT fleet management — equipment inventory, health monitoring, remote help sessions, and scheduled backups.</p>
      <div class="task-title">Reviewing device health</div>
      <ol><li>Open <strong>Atlas Sentinel</strong> to see equipment health scores and any active alerts; use Remote Help to start an attended remote session with a device.</li></ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Atlas WatchTower</span> <span class="tag">MIS</span> <span class="tag">Administrator</span></div>
    <div class="module-body">
      <p>Live application telemetry — request volume, slow queries, queue jobs, and exceptions — for keeping an eye on system health.</p>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Atlas Module Monitor</span> <span class="tag">MIS</span> <span class="tag">Administrator</span></div>
    <div class="module-body">
      <p>Per-module health and usage metrics across the whole platform, with a maturity scoring radar chart.</p>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Google Workspace Lifecycle</span> <span class="tag">MIS</span></div>
    <div class="module-body">
      <p>Keeps official Google accounts in sync with employee/student records — proposing corrections you review before they apply, and automatically provisioning or suspending accounts as employment/enrollment status changes.</p>
      <div class="task-title">Reviewing a correction proposal</div>
      <ol><li>Open a pending discrepancy proposal, review the suggested change, and approve or reject it.</li></ol>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Dyna AI Assistant</span> <span class="tag">CD/MANCOM</span> <span class="tag">Release Candidate</span></div>
    <div class="module-body">
      <p>A permission-gated conversational assistant for institutional questions (headcount, leave trends, and more), currently available to CD/MANCOM users. Marked <strong>Release Candidate</strong> — not yet a general production feature.</p>
    </div>
  </div>

  <div class="module-card">
    <div class="module-header"><span class="module-name">Profile</span> <span class="tag">All Employees</span></div>
    <div class="module-body">
      <p>Manage your name display preferences, specialization, profile photo, and digital signature.</p>
      <div class="task-title">Updating your photo/signature</div>
      <ol><li>Go to <strong>Profile</strong>, upload a new photo, or manage your digital signature and PIN used for approvals.</li></ol>
      <div class="tip-box">Your email and password aren't editable here — email is HR-managed and sign-in is via Google, so there's no separate password to change.</div>
    </div>
  </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- SECTION 3 — COMMON WORKFLOWS AT A GLANCE                                --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="page-break">
  <div class="section-label">Section 3</div>
  <h1>Common Workflows at a Glance</h1>
  <p>Many different requests in Atlas follow the same shape. These illustrations show that shared pattern using three real examples — once you recognize it, most other approval-based modules in this guide will feel familiar.</p>

  <h2>Filing and Approving a Leave Application</h2>
  <div class="diagram">
    <table class="diagram-row">
      <tr>
        <td><div class="diagram-title">1. You File</div><div class="diagram-note">Select leave type, dates, and reason; submit</div></td>
        <td class="arrow">→</td>
        <td><div class="diagram-title">2. Division Chief Approves</div><div class="diagram-note">First-stage review</div></td>
        <td class="arrow">→</td>
        <td><div class="diagram-title">3. HR Approves</div><div class="diagram-note">Final approval</div></td>
      </tr>
    </table>
    <table class="diagram-row">
      <tr>
        <td><div class="diagram-title">4. DTR Updates Automatically</div><div class="diagram-note">No separate DTR request needed</div></td>
        <td class="arrow">→</td>
        <td><div class="diagram-title">5. Leave Credit Deducted</div><div class="diagram-note">Reflected on your ledger</div></td>
        <td class="arrow">→</td>
        <td><div class="diagram-title">Done</div><div class="diagram-note">Status visible any time on your Leave Applications page</div></td>
      </tr>
    </table>
  </div>

  <h2>Requesting an HR Certificate</h2>
  <div class="diagram">
    <table class="diagram-row">
      <tr>
        <td><div class="diagram-title">1. You Request</div><div class="diagram-note">Choose certificate type and delivery method</div></td>
        <td class="arrow">→</td>
        <td><div class="diagram-title">2. HR Triages</div><div class="diagram-note">Accepts, or asks for more info</div></td>
        <td class="arrow">→</td>
        <td><div class="diagram-title">3. Prepared</div><div class="diagram-note">Content generated from your official records</div></td>
      </tr>
    </table>
    <table class="diagram-row">
      <tr>
        <td><div class="diagram-title">4A. HR-Authorized</div><div class="diagram-note">Straight to issuance</div></td>
        <td class="arrow">or</td>
        <td><div class="diagram-title">4B. Needs OCD Signature</div><div class="diagram-note">Routed to the Approval Inbox</div></td>
        <td class="arrow">→</td>
        <td><div class="diagram-title">5. Issued</div><div class="diagram-note">Download, QR-verified PDF, or pickup</div></td>
      </tr>
    </table>
  </div>

  <h2>Filing an IT Job Request</h2>
  <div class="diagram">
    <table class="diagram-row">
      <tr>
        <td><div class="diagram-title">1. You File</div><div class="diagram-note">Describe the issue; 3-day advance rule for event support</div></td>
        <td class="arrow">→</td>
        <td><div class="diagram-title">2. Division Chief Approves</div><div class="diagram-note">First-stage review</div></td>
        <td class="arrow">→</td>
        <td><div class="diagram-title">3. OCD Approves</div><div class="diagram-note">Second-stage review</div></td>
      </tr>
    </table>
    <table class="diagram-row">
      <tr>
        <td><div class="diagram-title">4. MIS Assesses &amp; Prioritizes</div><div class="diagram-note">Assigned in the priority queue</div></td>
        <td class="arrow">→</td>
        <td><div class="diagram-title">5. Resolved</div><div class="diagram-note">You're notified of completion</div></td>
        <td class="arrow">→</td>
        <td><div class="diagram-title">6. You Rate It</div><div class="diagram-note">CSM feedback survey</div></td>
      </tr>
    </table>
  </div>

  <div class="tip-box"><strong>Recognizing the pattern.</strong> File → stage 1 approval → (sometimes) stage 2 approval → outcome (issued document, resolved ticket, updated record) → optional feedback. Facility/Vehicle Requests, Gate Passes, Travel, PPMP, and Procurement all follow this same shape with different approvers.</div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- SECTION 4 — FREQUENTLY ASKED QUESTIONS                                  --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="page-break">
  <div class="section-label">Section 4</div>
  <h1>Frequently Asked Questions</h1>

  <h3>I can't sign in.</h3>
  <p>Make sure you're using your official <code>@crc.pshs.edu.ph</code> Google account, not a personal Gmail. If your account genuinely should exist and still won't sign in, contact HR (to confirm your account exists) or MIS (to confirm your email is correctly set up in Atlas).</p>

  <h3>A module or button I need isn't showing up.</h3>
  <p>Atlas only shows what your account's roles and permissions allow. If you believe you should have access to something, ask your HR Office or MIS to review your account's role assignment — this guide describes what's available to each role, but the actual grant has to be made on your account.</p>

  <h3>My leave/request status hasn't changed in a while.</h3>
  <p>Check who the next approver is for that request — most workflows show this on the request's detail view. If it's been unusually long, follow up with that approver directly, or with the Approval Inbox owner for that stage.</p>

  <h3>A file/photo upload isn't working.</h3>
  <p>Uploads go through your browser's camera or file picker — make sure you've granted camera/file access when prompted, and that the file isn't larger than the module's stated limit (for example, Chat attachments are capped at 10 MB). If it still fails, file an Error Report so MIS has the details.</p>

  <h3>I made a mistake on a form I already submitted.</h3>
  <p>Most submitted records (IPCR ratings, class record scores after locking, filed leave applications) are intentionally hard to edit after submission, to keep an honest history. Depending on the module, you can usually cancel a still-pending request and refile, or ask the relevant office (HR, CID, Registrar) to make a formal correction on your behalf.</p>

  <h3>How do notifications work?</h3>
  <p>You'll see an in-app bell for things like approvals waiting on you and campus announcements. If you've allowed your browser's notification permission, you'll also get a push notification even when Atlas isn't your active tab.</p>

  <h3>Something looks broken or wrong.</h3>
  <p>Use the in-app Error Report — it captures a screenshot automatically and gives you a reference number to quote when following up with MIS.</p>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- SECTION 5 — GLOSSARY                                                    --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="page-break">
  <div class="section-label">Section 5</div>
  <h1>Glossary</h1>
  <table>
    <tr><th style="width:22%">Term</th><th>Meaning in Atlas</th></tr>
    @foreach ([
      ['ACIDAA', 'Assistant Chief of the Curriculum and Instruction Division for Academic Affairs — part of the faculty IPCR approval chain.'],
      ['ALP', 'Alternative Learning Program — the module for accredited student organizations and program governance.'],
      ['Approval Inbox', 'Your one queue for everything across Atlas that\'s waiting on your approval or signature.'],
      ['AtlasGo', 'The mobile app version of the Student Portal, for students and parents.'],
      ['Atlas Sentinel', 'The ICT fleet management and device health module used by MIS.'],
      ['AUH', 'Assistant Unit Head — an academic-unit-level role in Faculty Loading and related approvals.'],
      ['CID', 'Curriculum and Instruction Division.'],
      ['CSM', 'Client Satisfaction Measurement — the service-rating survey used after transactions like IT Job Requests.'],
      ['DTR', 'Daily Time Record — your combined attendance record from biometrics, online punch, leave, WFH, travel, and gate passes.'],
      ['Dyna', 'Atlas\'s AI assistant for institutional questions, currently limited to CD/MANCOM users.'],
      ['FAD', 'Finance and Administrative Division.'],
      ['GSU', 'General Services Unit.'],
      ['ILA', 'Independent Learning Activity — a grading component used in Class Records.'],
      ['MIS', 'Management Information System (ICT) — the role that maintains and supports Atlas itself.'],
      ['OCD', 'Office of the Campus Director.'],
      ['PDS', 'Personal Data Sheet — CSC Form 212, the standard government employee information form.'],
      ['PIN signing', 'Confirming your identity with your digital signature PIN before an approval or signed document is finalized.'],
      ['PISAY ID', 'A student\'s barcode ID used for gate attendance scanning.'],
      ['RBAC', 'Role-Based Access Control — how Atlas decides what each account can see and do.'],
      ['SALN', 'Statement of Assets, Liabilities, and Net Worth — an annual CSC filing requirement.'],
      ['SLA', 'Service-Level target, in business days, for processing document requests.'],
      ['WAT', 'Weekly Assessment Tracker — for plotting and monitoring assessment load in Class Records.'],
      ['WFH', 'Work From Home attendance and accomplishment reporting.'],
    ] as [$term, $meaning])
    <tr><td><strong>{{ $term }}</strong></td><td>{{ $meaning }}</td></tr>
    @endforeach
  </table>

</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- SECTION 6 — AI USAGE STATEMENT                                          --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="page-break">
  <div class="section-label">Section 6</div>
  <h1>AI Usage Statement</h1>

  <p>This section discloses the role of AI-based tools in building the Atlas platform and in producing this user guide, consistent with the disclosure carried in the Atlas Technical Documentation.</p>

  <h2>In System Development</h2>
  <p>Atlas is designed, implemented, and maintained by Junlou Tordos and Michael Francisco of the PSHS-CRC MIS team, who hold full authorship and editorial control over the platform's architecture, features, and code. AI-based development assistants — principally Anthropic's Claude and Claude Code — were used throughout implementation as coding, debugging, and code-review tools operating under direct human instruction and review. Every change was directed, evaluated, and accepted or rejected by the named developers before being committed to the codebase; no code was merged or deployed without human review.</p>

  <h2>In This Guide</h2>
  <p>This user guide was drafted with AI assistance based on the actual implemented modules, permissions, and workflows of Atlas, cross-checked against the platform's own Technical Documentation and source code rather than invented from general assumptions about what such a system might contain. The selection, structure, verification, and final approval of all content — including the accuracy of every instruction herein — remain the responsibility of the institutional author, Philippine Science High School - Caraga Region Campus in Butuan City, acting through its named developers.</p>

  <div class="info-box">
    <strong>Scope of disclosure.</strong> No content in this guide was generated without reference to the actual implemented system, and no AI tool had autonomous authority to add, remove, or publish content in this guide.
  </div>

  <div class="info-box" style="margin-top:18px;">
    <strong>End of guide.</strong> This is Edition {{ $document['edition'] }} of the Atlas User Guide, current as of {{ $document['snapshot_date'] }}. If a screen you see doesn't match what's described here, Atlas may have been updated since this edition was generated — check with MIS for the latest edition, or refer to your module's on-screen labels as the source of truth.
  </div>
</div>

</body>
</html>
