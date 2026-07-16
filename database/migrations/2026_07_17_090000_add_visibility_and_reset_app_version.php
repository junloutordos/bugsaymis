<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_versions', function (Blueprint $table) {
            $table->boolean('is_visible')->default(true)->after('is_current');
        });

        $features = [
            'Dashboard analytics, role-based navigation, unified approvals inbox, notifications, and audit-ready digital signatures',
            'Employee records, profiles, Personal Data Sheets, service records, leave, attendance, DTR, WFH, and online time punch',
            'Payroll processing, payslips, cashier disbursement, salary schedules, and compensation reporting',
            'IPCR/PMS planning, review, rating, calibration, approval, and performance reporting workflows',
            'Recruitment, vacancies, applicant tracking, qualification review, ranking, and appointment requirements',
            'Faculty loading, teaching assignments, overload approval, committee work, research advisories, and training records',
            'Class scheduling by section and faculty, AI schedule generation, conflict validation, automatic placement, and printable official schedules',
            'Class records, assessments, computed quarterly and annual grades, at-risk monitoring, imports, and PDF exports',
            'Student enrollment, attendance, health, guidance, discipline, residence hall, clearance, rewards, and parent-linked mobile services',
            'Library circulation, catalog management, inventory, borrower services, and reporting',
            'Procurement planning and transactions including PPMP, purchase requests, canvass, purchase orders, obligations, and disbursement vouchers',
            'General services for facilities, vehicles, work, service, messengerial, IT job, gate pass, and science laboratory requests',
            'Document tracking, official issuances, templates, routing, QR verification, private attachments, and electronic signing',
            'SALN preparation, review, submission, and reporting',
            'Activity management, attendance registration, participant certificates, evaluations, and analytics',
            'ICT equipment inventory, device agent monitoring, alerts, remote assistance, backups, and release management',
            'Real-time chat, in-app notifications, email notifications, web push, and approval reminders',
            'Technical documentation, module monitoring, operational metrics, error reporting, and administrative configuration',
            'Private AWS S3 media delivery, encrypted AWS infrastructure, automated backups, security controls, and CI/CD deployment',
        ];

        DB::table('app_versions')->update(['is_current' => false, 'is_visible' => false]);
        DB::table('app_versions')->updateOrInsert(
            ['version' => '1.0.0'],
            [
                'date' => '2026-07-17',
                'remarks' => 'Initial consolidated production release of the BugSayMis campus management information system.',
                'changes' => json_encode(['features' => $features, 'fixes' => [], 'improvements' => []]),
                'is_current' => true,
                'is_visible' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('app_versions')->update(['is_visible' => true]);
        Schema::table('app_versions', function (Blueprint $table) {
            $table->dropColumn('is_visible');
        });
    }
};
