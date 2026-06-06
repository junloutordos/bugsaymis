<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            // Workflow status — default 'approved' preserves all existing records
            $table->string('status')->default('approved')->after('requested_by');

            // Division metadata
            $table->unsignedBigInteger('division_id')->nullable()->after('status');
            $table->foreign('division_id')->references('id')->on('divisions')->nullOnDelete();

            // Supplemental track
            $table->boolean('is_supplemental')->default(false)->after('division_id');
            $table->boolean('ppmp_checked')->default(true)->after('is_supplemental');
            $table->string('supplemental_status')->nullable()->after('ppmp_checked');
            $table->unsignedBigInteger('supplemental_budget_officer_id')->nullable()->after('supplemental_status');
            $table->timestamp('supplemental_budget_officer_at')->nullable()->after('supplemental_budget_officer_id');
            $table->foreign('supplemental_budget_officer_id')->references('id')->on('users')->nullOnDelete();

            // Division Chief stage
            $table->unsignedBigInteger('division_chief_id')->nullable()->after('supplemental_budget_officer_at');
            $table->text('division_chief_remarks')->nullable()->after('division_chief_id');
            $table->timestamp('division_chief_at')->nullable()->after('division_chief_remarks');
            $table->foreign('division_chief_id')->references('id')->on('users')->nullOnDelete();

            // Procurement Officer stage
            $table->unsignedBigInteger('procurement_officer_id')->nullable()->after('division_chief_at');
            $table->timestamp('procurement_officer_at')->nullable()->after('procurement_officer_id');
            $table->string('assigned_pr_number')->nullable()->after('procurement_officer_at');
            $table->foreign('procurement_officer_id')->references('id')->on('users')->nullOnDelete();

            // OCD stage
            $table->unsignedBigInteger('ocd_id')->nullable()->after('assigned_pr_number');
            $table->timestamp('ocd_at')->nullable()->after('ocd_id');
            $table->text('ocd_remarks')->nullable()->after('ocd_at');
            $table->foreign('ocd_id')->references('id')->on('users')->nullOnDelete();

            // Rejection
            $table->unsignedBigInteger('rejected_by')->nullable()->after('ocd_remarks');
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
            $table->foreign('rejected_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            $table->dropForeign(['division_id']);
            $table->dropForeign(['supplemental_budget_officer_id']);
            $table->dropForeign(['division_chief_id']);
            $table->dropForeign(['procurement_officer_id']);
            $table->dropForeign(['ocd_id']);
            $table->dropForeign(['rejected_by']);

            $table->dropColumn([
                'status', 'division_id', 'is_supplemental', 'ppmp_checked',
                'supplemental_status', 'supplemental_budget_officer_id', 'supplemental_budget_officer_at',
                'division_chief_id', 'division_chief_remarks', 'division_chief_at',
                'procurement_officer_id', 'procurement_officer_at', 'assigned_pr_number',
                'ocd_id', 'ocd_at', 'ocd_remarks',
                'rejected_by', 'rejected_at', 'rejection_reason',
            ]);
        });
    }
};
