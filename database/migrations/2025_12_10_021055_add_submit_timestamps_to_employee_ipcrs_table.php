<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_ipcrs', function (Blueprint $table) {
            $table->timestamp('submitted_for_review_at')->nullable()->after('status');
            $table->timestamp('submitted_for_rating_at')->nullable()->after('submitted_for_review_at');
            $table->timestamp('submitted_rating_at')->nullable()->after('submitted_for_rating_at');
            $table->timestamp('submitted_for_pmtreview_at')->nullable()->after('submitted_rating_at');
        });
    }

    public function down(): void
    {
        Schema::table('employee_ipcrs', function (Blueprint $table) {
            $table->dropColumn('submitted_for_review_at');
            $table->dropColumn('submitted_for_rating_at');
            $table->dropColumn('submitted_rating_at');
            $table->dropColumn('submitted_for_pmtreview_at');
        });
    }
};
