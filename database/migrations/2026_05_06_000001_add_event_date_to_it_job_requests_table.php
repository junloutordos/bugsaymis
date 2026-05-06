<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('it_job_requests', function (Blueprint $table) {
            $table->date('event_date')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('it_job_requests', function (Blueprint $table) {
            $table->dropColumn('event_date');
        });
    }
};
