<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_ipcrs', function (Blueprint $table) {
            $table->timestamp('submitted_to_hr_at')->nullable()->after('submitted_for_pmtreview_at');
            $table->timestamp('director_signed_at')->nullable()->after('submitted_to_hr_at');
            $table->string('director_signature')->nullable()->after('director_signed_at');
        });
    }

    public function down(): void
    {
        Schema::table('employee_ipcrs', function (Blueprint $table) {
            $table->dropColumn(['submitted_to_hr_at', 'director_signed_at', 'director_signature']);
        });
    }
};
