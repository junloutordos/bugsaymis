<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('it_job_requests', function (Blueprint $table) {
            // Populated only when category is "Posting to Website" or "Posting to Social Media".
            // Determines routing: 'financial' → Division Chief, 'general' → Information Officer.
            $table->enum('posting_type', ['financial', 'general'])->nullable()->after('event_date');
        });
    }

    public function down(): void
    {
        Schema::table('it_job_requests', function (Blueprint $table) {
            $table->dropColumn('posting_type');
        });
    }
};
