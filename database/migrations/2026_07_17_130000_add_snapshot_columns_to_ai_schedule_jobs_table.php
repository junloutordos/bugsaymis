<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot support for AI schedule applies: `replaced_schedules` stores the
     * tentative class_schedules rows deleted by an apply (so the apply can be
     * rolled back), `restored_at` records when a job's snapshot was restored.
     */
    public function up(): void
    {
        Schema::table('ai_schedule_jobs', function (Blueprint $table) {
            $table->json('replaced_schedules')->nullable()->after('generated_schedules');
            $table->timestamp('restored_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('ai_schedule_jobs', function (Blueprint $table) {
            $table->dropColumn(['replaced_schedules', 'restored_at']);
        });
    }
};
