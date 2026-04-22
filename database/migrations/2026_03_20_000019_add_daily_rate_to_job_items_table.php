<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_items', function (Blueprint $table) {
            $table->decimal('daily_rate', 10, 2)->nullable()->after('monthly_salary');
        });
    }

    public function down(): void
    {
        Schema::table('job_items', function (Blueprint $table) {
            $table->dropColumn('daily_rate');
        });
    }
};
