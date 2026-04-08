<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add indexes to hot columns used in WHERE clauses, ORDER BY, and JOINs.
 * Addresses the following frequently-executed query patterns:
 *
 *  - employee_ipcrs: filter by status, rating_period; order by timestamp columns
 *  - users: filter by status (active/inactive), division_id FK
 *  - employee_ipcrs_plan: filter by ipcr_id; AVG(sup_average) subquery
 */
return new class extends Migration
{
    public function up(): void
    {
        // employee_ipcrs -------------------------------------------------------
        Schema::table('employee_ipcrs', function (Blueprint $table) {
            // status is used in every controller's whereIn('status', [...])
            if (!$this->indexExists('employee_ipcrs', 'employee_ipcrs_status_index')) {
                $table->index('status', 'employee_ipcrs_status_index');
            }
            // rating_period is used in batch-submit filters
            if (!$this->indexExists('employee_ipcrs', 'employee_ipcrs_rating_period_index')) {
                $table->index('rating_period', 'employee_ipcrs_rating_period_index');
            }
            // Composite for the common (status, rating_period) filter pattern
            if (!$this->indexExists('employee_ipcrs', 'employee_ipcrs_status_period_index')) {
                $table->index(['status', 'rating_period'], 'employee_ipcrs_status_period_index');
            }
            // Timestamp columns used in ORDER BY
            if (!$this->indexExists('employee_ipcrs', 'employee_ipcrs_submitted_to_hr_at_index')) {
                $table->index('submitted_to_hr_at', 'employee_ipcrs_submitted_to_hr_at_index');
            }
            if (!$this->indexExists('employee_ipcrs', 'employee_ipcrs_submitted_for_pmtreview_at_index')) {
                $table->index('submitted_for_pmtreview_at', 'employee_ipcrs_submitted_for_pmtreview_at_index');
            }
        });

        // employee_ipcrs_plan --------------------------------------------------
        // ipcr_id is the grouping key for all sup_average AVG() subqueries
        Schema::table('employee_ipcrs_plan', function (Blueprint $table) {
            if (!$this->indexExists('employee_ipcrs_plan', 'employee_ipcrs_plan_ipcr_id_index')) {
                $table->index('ipcr_id', 'employee_ipcrs_plan_ipcr_id_index');
            }
            // sup_average is used in AVG() aggregation subqueries
            if (!$this->indexExists('employee_ipcrs_plan', 'employee_ipcrs_plan_sup_average_index')) {
                $table->index('sup_average', 'employee_ipcrs_plan_sup_average_index');
            }
        });

        // users ----------------------------------------------------------------
        Schema::table('users', function (Blueprint $table) {
            // status used to guard inactive accounts
            if (!$this->indexExists('users', 'users_status_index')) {
                $table->index('status', 'users_status_index');
            }
        });

        // audit_logs -----------------------------------------------------------
        // created_at for time-range report queries (auditable_type+id composite already exists)
        Schema::table('audit_logs', function (Blueprint $table) {
            if (!$this->indexExists('audit_logs', 'audit_logs_created_at_index')) {
                $table->index('created_at', 'audit_logs_created_at_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_ipcrs', function (Blueprint $table) {
            $table->dropIndexIfExists('employee_ipcrs_status_index');
            $table->dropIndexIfExists('employee_ipcrs_rating_period_index');
            $table->dropIndexIfExists('employee_ipcrs_status_period_index');
            $table->dropIndexIfExists('employee_ipcrs_submitted_to_hr_at_index');
            $table->dropIndexIfExists('employee_ipcrs_submitted_for_pmtreview_at_index');
        });
        Schema::table('employee_ipcrs_plan', function (Blueprint $table) {
            $table->dropIndexIfExists('employee_ipcrs_plan_ipcr_id_index');
            $table->dropIndexIfExists('employee_ipcrs_plan_sup_average_index');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndexIfExists('users_status_index');
        });
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndexIfExists('audit_logs_created_at_index');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        // SQLite does not support SHOW INDEX — skip check (indexes are safe to re-add on SQLite)
        if (\DB::getDriverName() === 'sqlite') {
            return false;
        }

        return collect(\DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]))->isNotEmpty();
    }
};
