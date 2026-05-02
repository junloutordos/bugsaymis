<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('app_versions')->truncate();

        DB::table('app_versions')->insert([
            'version'    => '1.0.0',
            'date'       => '2026-05-02',
            'remarks'    => $this->remarks(),
            'is_current' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('app_versions')->where('version', '1.0.0')->delete();
    }

    private function remarks(): string
    {
        return <<<TEXT
Version 1.0.0 — Initial Production Release

CRCMIS (Centralized Resource and Campus Management Information System) is the official digital platform of the Philippine Science High School – Caraga Region Campus (PSHS-CRC) in Butuan City. This release unifies all campus operations into a single secure and integrated system.

─── HR & PERSONNEL MANAGEMENT ───
• Employee Records — 201 Files, personal data, employment details, designations, and status management
• Personal Data Sheet (PDS) — CSC-compliant digital PDS covering education, work experience, eligibility, trainings, voluntary work, and references
• Leave Management — Leave application workflow following CSC Form No. 6 (Revised 2020); multi-level approval (Division Chief → HR → OCD); automatic weekend exclusion and leave credit deduction
• Leave Credits — Automated accrual for non-teaching staff; service credit tracking for teaching personnel; full transaction audit trail and balance history
• Daily Time Record (DTR) — Biometric sync integration, manual DTR encoding, checklist-based entry, and monthly DTR report generation
• Work From Home (WFH) — WFH attendance monitoring, daily accomplishment reporting, workback processing, and OCD approval workflow
• Employee Schedules — Individual work schedule assignment and management
• SALN — Statement of Assets, Liabilities and Net Worth; employee submission, HR review, OCD approval, and digital filing using the latest CSC format

─── PERFORMANCE MANAGEMENT ───
• IPCR — Individual Performance Commitment and Review with a complete 5-stage workflow: Employee submission → Division Chief review → HR endorsement → PMT evaluation → OCD/Director signing with e-signature support; weighted average computation (Strategic 30%, Core 55%, Support 15%)
• PMS (PMT) — Performance Management Team dashboards, evaluation reports, and batch processing
• Individual Development Plans (IDP) — Training needs assessment and individual development plan management

─── RECRUITMENT & REWARDS ───
• Recruitment & Selection — Full recruitment lifecycle: job postings, applicant tracking, document requirements, interview scheduling, ranking summaries, and placement recording
• Rewards & Recognition — Nomination workflows, multi-criteria evaluation and scoring, approval routing, and recognition log

─── LEARNING & DEVELOPMENT ───
• Training Records — Training sessions, participant tracking, post-training evaluation, and certificate management
• Learning Programs — Structured employee development programs and enrollments

─── FACULTY & ACADEMIC ───
• Faculty Loading — AI-assisted schedule generation with conflict detection and auto-resolution; overload computation; section-subject-faculty assignment; official time and designation tracking
• Class Schedules — Classroom and section schedule management per academic term and school year
• Student Gate Attendance — Real-time student entry and exit tracking via barcode or QR code scanning; integrated with student enrollment records; attendance history and reporting

─── LIBRARY ───
• Collections — Book and resource cataloging with category classification
• Borrowing System — Borrow and return management with due date tracking and overdue monitoring
• Library Attendance — Daily patron attendance logging and reports

─── GUIDANCE & HEALTH ───
• Guidance Consultations — Student and employee counseling and consultation records
• Session Reports — Automated and printable guidance session documentation
• Physician Schedule — Campus health appointment and physician scheduling

─── REQUESTS & GENERAL SERVICES ───
• IT Job Requests (ITJR) — Technical service request lifecycle with MIS assessment, tracking logs, and action documentation
• Vehicle Requests — Pool vehicle booking, driver assignment, and approval workflow
• Facility Requests — Venue and facility reservation with availability management
• Service Requests — General maintenance and campus service requests
• Messengerial Requests — Document courier and delivery coordination

─── PROCUREMENT ───
• PPMP — Project Procurement Management Plan; item encoding, budget tracking, and status history
• Procurement Records — Purchase and procurement transaction management

─── DOCUMENT TRACKING ───
• Document Routing — Official document tracking with routing history, signatory snapshots, and status updates
• Document Types — Configurable document classification and template management

─── ACTIVITY MANAGEMENT (AMS) ───
• Campus Activity Planning — Activity creation, co-proponents, target participants, student attendance per activity, and post-activity evaluation

─── STUDENT & PARENT SERVICES ───
• Student Records — Enrollment, section assignment, grade level, and student profile management
• Parent Contacts — Emergency contact and parent communication records

─── ADMINISTRATION & SYSTEM ───
• Role-Based Access Control (RBAC) — Dynamic roles and granular permission management; module-level and action-level access control; Administrator role bypasses all restrictions
• User Management — Account provisioning, role assignment, and employee account status management
• Real-Time Chat — Instant messaging between users powered by Laravel Echo and Soketi
• Audit Logs — Comprehensive system activity and data change tracking
• Organizational Structure — Divisions, offices, and unit hierarchy management with chief assignment
• Salary Schedules — Salary grade and step matrix following the Salary Standardization Law (SSL)
• Dashboard Analytics — System-wide key metrics, statistics, and operational summaries
• Campus Info — Campus profile, official information, and digital resource directory

Powered by Laravel 12, Vue 3 (Composition API), Inertia.js 2, and deployed on AWS.
TEXT;
    }
};
