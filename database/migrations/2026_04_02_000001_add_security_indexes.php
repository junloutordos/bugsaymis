<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // library_attendances — queried heavily by student_id, scanned_at date range, badge scan lookups
        Schema::table('library_attendances', function (Blueprint $table) {
            if (!$this->hasIndex('library_attendances', 'library_attendances_student_id_index')) {
                $table->index('student_id', 'library_attendances_student_id_index');
            }
            if (!$this->hasIndex('library_attendances', 'library_attendances_scanned_at_index')) {
                $table->index('scanned_at', 'library_attendances_scanned_at_index');
            }
            if (!$this->hasIndex('library_attendances', 'library_attendances_pisay_systemid_index')) {
                $table->index('pisay_systemid', 'library_attendances_pisay_systemid_index');
            }
        });

        // gatepass — queried by badgeNumber and status
        Schema::table('gatepass', function (Blueprint $table) {
            if (!$this->hasIndex('gatepass', 'gatepass_badgenumber_index')) {
                $table->index('badgeNumber', 'gatepass_badgenumber_index');
            }
            if (!$this->hasIndex('gatepass', 'gatepass_status_index')) {
                $table->index('status', 'gatepass_status_index');
            }
            if (!$this->hasIndex('gatepass', 'gatepass_gatepass_date_index')) {
                $table->index('gatepass_date', 'gatepass_gatepass_date_index');
            }
        });

        // guidance_consultations — queried by requestor, assigned_personnel, status
        Schema::table('guidance_consultations', function (Blueprint $table) {
            if (!$this->hasIndex('guidance_consultations', 'guidance_consultations_status_index')) {
                $table->index('status', 'guidance_consultations_status_index');
            }
        });

        // consultations — queried by patient, date, type
        Schema::table('consultations', function (Blueprint $table) {
            if (!$this->hasIndex('consultations', 'consultations_created_at_index')) {
                $table->index('created_at', 'consultations_created_at_index');
            }
        });

        // audit_logs — already has indexes, but add action index for filtering
        if (!$this->hasIndex('audit_logs', 'audit_logs_action_index')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->index('action', 'audit_logs_action_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('library_attendances', function (Blueprint $table) {
            $table->dropIndex('library_attendances_student_id_index');
            $table->dropIndex('library_attendances_scanned_at_index');
            $table->dropIndex('library_attendances_pisay_systemid_index');
        });

        Schema::table('gatepass', function (Blueprint $table) {
            $table->dropIndex('gatepass_badgenumber_index');
            $table->dropIndex('gatepass_status_index');
            $table->dropIndex('gatepass_gatepass_date_index');
        });

        Schema::table('guidance_consultations', function (Blueprint $table) {
            $table->dropIndex('guidance_consultations_status_index');
        });

        Schema::table('consultations', function (Blueprint $table) {
            $table->dropIndex('consultations_created_at_index');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_action_index');
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $indexes = \DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }
};
