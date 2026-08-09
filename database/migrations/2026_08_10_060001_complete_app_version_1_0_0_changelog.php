<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The 1.0.0 changelog only ever recorded a "features" list; "fixes" and
 * "improvements" were left empty even though a large amount of pre-2.0.0
 * bugfix and hardening work shipped under that same static version number.
 * This backfills both arrays for historical completeness.
 */
return new class extends Migration
{
    public function up(): void
    {
        $fixes = [
            'Corrected leave application approval routing so Division Chiefs only act on requests from their own division',
            'Fixed Class Record grade computation and score-saving reliability issues affecting quarterly and annual grades',
            'Resolved Daily Time Record (DTR) schedule-based late/undertime/overtime computation edge cases',
            'Fixed Gate Pass Division Chief approvals failing due to an incorrect role check',
            'Corrected Faculty Loading class-schedule calendar rendering and print-view errors for unassigned groups',
            'Moved chat attachment delivery to secure private storage instead of public/multipart uploads',
            'Resolved Personal Data Sheet (PDS) Excel export data errors, including blank-field and date-parsing issues',
            'Fixed CSV bulk-import validation and error handling across PDS Trainings, Supply, and Property modules',
            'Corrected ICT Equipment inventory review-status filters and edit-form validation error display',
            'Fixed sidebar navigation links pointing to routes the signed-in role could not actually access',
            'Resolved a database backup verification issue that produced false "backup missing" alerts',
            'Fixed Vehicle Request signature images failing to render on printed forms',
            'Corrected mobile Parent/Student accounts being mixed into employee-only lists and notifications',
            'Fixed the ICT PMS (equipment maintenance) approval workflow\'s broken Edit/Delete actions',
        ];

        $improvements = [
            'Consolidated Committee membership and rating data between Faculty Loading and Performance Management into one shared source of truth',
            'Added QR-code verification and digital signature validation to printable official forms and requests',
            'Hardened file uploads and rich-text content system-wide against unsafe file types and cross-site scripting',
            'Added skeleton loading states and smoother page transitions throughout the admin interface',
            'Added Class Record section-level assessment calendars with daily/weekly plotting caps (Weekly Assessment Tracker)',
            'Improved Faculty Loading class scheduling with AI-assisted auto-placement, conflict detection, and swap/reassignment tools',
            'Added Employee Digital ID with a QR-based mobile wallet card',
            'Strengthened production security posture with encrypted infrastructure, audit logging, rate limiting, and dependency patching',
            'Improved DTR advance-entry support for Contract-of-Service (COS) employees',
            'Added online time-punch attendance with liveness detection and geofencing',
            'Streamlined Supply & Property with CSV import/export and COA-compliant reporting',
            'Unified the dashboard color palette and visual consistency across modules',
        ];

        DB::table('app_versions')->where('version', '1.0.0')->update([
            'changes' => json_encode(['features' => $this->currentFeatures(), 'fixes' => $fixes, 'improvements' => $improvements]),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('app_versions')->where('version', '1.0.0')->update([
            'changes' => json_encode(['features' => $this->currentFeatures(), 'fixes' => [], 'improvements' => []]),
            'updated_at' => now(),
        ]);
    }

    /**
     * The features array set by the 2026-07-17 migration — preserved as-is,
     * only fixes/improvements are being backfilled here.
     */
    private function currentFeatures(): array
    {
        $row = DB::table('app_versions')->where('version', '1.0.0')->first(['changes']);
        $changes = $row?->changes ? json_decode($row->changes, true) : null;

        return $changes['features'] ?? [];
    }
};
