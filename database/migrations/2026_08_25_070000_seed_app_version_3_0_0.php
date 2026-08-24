<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $features = [
            'SOS Emergency Button — a floating SOS trigger on Atlas web, the Student Portal, and AtlasGo mobile, gated to the campus geofence, with tiered SMS/email escalation to responders and the reporting person\'s own emergency contact, and a real-time Command Center for DRRM/Security to monitor and respond',
            'Emergency Alert Broadcast — Command Center can broadcast a campus-wide emergency alert; every signed-in user sees a real-time site-wide red border and takeover banner until the alert is resolved',
            'Substitution & Acting-As — nominate and approve a substitute for a leave or travel filing; the substitute gets a persistent "Acting As" banner and temporary access to the original filer\'s pending items, automatically revoked if the leave is cancelled',
            'Flexible multi-criteria Issuance recipients — target an issuance by role, office, section, grade level, or individual student (including students), with the ability to add recipients after release and notify only the new additions',
            'Announcements extended to Employees, Students, and Parents — audience picker by group, push notifications to AtlasGo via FCM, and a shared pending-notices queue with acknowledgment tracking on both web and mobile',
            'AtlasGo published live — the Android app is on the Google Play Store and the iOS app has been submitted for App Store review, alongside a premium UI redesign (hero card, live SOS status, restructured navigation), a digital student ID card (photo, LRN, OCD signature, emergency contact), self-service profile-update requests with registrar review, and an announcements/emergency card feed with detail sheets and history',
            'Learn premium redesign — course cover photos, a setup-progress checklist, and redesigned course/module/announcement pages on both the faculty and Student Portal sides',
            'ALP (Adopt-a-Learner Program) rosters — clickable Active Members and Unassigned Grades 7-10 dashboard cards open dedicated roster pages, each with a PDF export using dynamic filters and the official Atlas letterhead',
            'AMS Evaluation Period + Comprehensive Report — open or close the evaluation window per activity, track attendance per day for multi-day activities (employees and students), and generate a full attendance/evaluation report for printing',
            'Agency Org Outcome hierarchy — IPCR outcomes are now parent/child (was a flat list), with a tree-shaped admin page and outcome picker',
            'DOST Strategic Plan — pillars, strategies, and sub-strategies linked to agency outcomes, with an interactive management page',
            'DTR Penned-Entry HR Approval + Hazard Report — HR can review and approve penned COS advance-entry DTR records and generate a Hazard Report PDF',
            'Per-office QR client-satisfaction survey (CSM) for walk-in feedback',
        ];

        $fixes = [
            'Fixed the Adjusted Class Schedule "Shortened Classes" flow falsely blocking saves with a "room conflict" or "faculty conflict" error, caused by real section-timetable drift from the idealized bell-schedule grid',
            'AUH\'s own leave now routes to the CID Chief instead of ACIDAA',
            'Fixed the Approvals Inbox showing a generic error instead of the real signature-PIN failure, added a re-sign option, and blocked the leave workflow from silently continuing on a failed PIN',
            'Fixed Academic Unit Heads not seeing pending leave items in their Approval Inbox, and a 403 when approving from it',
            'Fixed a conflict blocking Division Chief leave approval immediately after the AUH clears it',
            'Auto-grant the annual Vacation Leave entitlement instead of requiring manual setup',
            'Security audit remediation — corrected S3 storage-disk usage, mPDF temp-directory handling, dependency CVEs, and converted the remaining file-upload flows to base64 for Cloudflare WAF compliance',
            'Fixed a security IDOR in the Announcements/AtlasGo media proxy',
            'Fixed several Weekly Assessment Tracker (WAT) bugs — the PDF leaving blank page gaps, major-assessment tagging using a stale cap instead of the actual plotted count, and shared PEHM records showing the creator\'s raw subject name instead of "PEHM {n}"',
            'Consolidated duplicate PEHM co-teacher sections and fixed their schedule-conflict check',
            'Fixed an inverted teacher-attendance NFC tap window that closed access before class actually ended',
            'Fixed AMS certificate generation failing silently and running on the request thread instead of a background job',
            'Students module now requires explicit permission to delete a student and blocks deletion when enrollment records exist',
            'Restricted Gate Pass filing/editing to today or future dates only',
            'Fixed a stale student photo on the Health Consultations page',
        ];

        $improvements = [
            'Queue worker split into "default" and "bulk" lanes so heavy jobs (certificate generation, evaluation-link emails) no longer delay regular queue traffic',
            'Work Request print button now appears once the request has been rated via CSM',
            'Added a Sex at Birth field to AMS walk-in evaluations and its export',
            'Issuances Show page redesigned — wider layout, clearer letterhead and sidebar grouping',
        ];

        DB::table('app_versions')->update(['is_current' => false]);
        DB::table('app_versions')->updateOrInsert(
            ['version' => '3.0.0'],
            [
                'date' => '2026-08-25',
                'remarks' => 'Version 3.0.0 — SOS Emergency Alerts, Substitution & Acting-As, flexible Issuance recipients, campus-wide Announcements, and AtlasGo mobile going live on both app stores.',
                'changes' => json_encode(['features' => $features, 'fixes' => $fixes, 'improvements' => $improvements]),
                'is_current' => true,
                'is_visible' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('app_versions')->where('version', '3.0.0')->delete();
        DB::table('app_versions')->where('version', '2.1.0')->update(['is_current' => true]);
    }
};
