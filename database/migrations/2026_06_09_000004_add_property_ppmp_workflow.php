<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Expand status column (varchar 20 → 50) and add new statuses
        DB::statement("ALTER TABLE ppmp MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'draft'");

        // Add 'property' to ppmp_type enum
        DB::statement("ALTER TABLE ppmp MODIFY COLUMN ppmp_type ENUM('unit','division','property') NOT NULL DEFAULT 'unit'");

        Schema::table('ppmp', function (Blueprint $table) {
            // Link Division PPMPs → Property PPMP
            $table->unsignedBigInteger('property_ppmp_id')->nullable()->after('division_ppmp_id');

            // OCD review columns
            $table->timestamp('ocd_reviewed_at')->nullable()->after('budget_officer_remarks');
            $table->unsignedBigInteger('ocd_reviewed_by')->nullable()->after('ocd_reviewed_at');
            $table->text('ocd_remarks')->nullable()->after('ocd_reviewed_by');

            // DBM submission columns
            $table->timestamp('submitted_to_dbm_at')->nullable()->after('ocd_remarks');
            $table->unsignedBigInteger('submitted_to_dbm_by')->nullable()->after('submitted_to_dbm_at');

            $table->foreign('property_ppmp_id')->references('id')->on('ppmp')->nullOnDelete();
            $table->foreign('ocd_reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('submitted_to_dbm_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ppmp', function (Blueprint $table) {
            $table->dropForeign(['property_ppmp_id']);
            $table->dropForeign(['ocd_reviewed_by']);
            $table->dropForeign(['submitted_to_dbm_by']);
            $table->dropColumn([
                'property_ppmp_id',
                'ocd_reviewed_at', 'ocd_reviewed_by', 'ocd_remarks',
                'submitted_to_dbm_at', 'submitted_to_dbm_by',
            ]);
        });

        DB::statement("ALTER TABLE ppmp MODIFY COLUMN ppmp_type ENUM('unit','division') NOT NULL DEFAULT 'unit'");
        DB::statement("ALTER TABLE ppmp MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'draft'");
    }
};
