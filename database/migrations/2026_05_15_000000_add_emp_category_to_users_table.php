<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * emp_category has been read/written across the codebase for a long time
 * (e.g. DashboardController, HRDashboardController) but was never created by
 * a tracked migration — it exists in production only via an untracked manual
 * schema change. This adds it where missing (a no-op on environments, like
 * production, where the column already exists) so dev matches prod.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'emp_category')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('emp_category', 100)->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'emp_category')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('emp_category');
        });
    }
};
