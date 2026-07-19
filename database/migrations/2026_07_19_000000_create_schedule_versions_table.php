<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_versions', function (Blueprint $table) {
            $table->id();

            // Scope — whole term, tentative rows only (see ScheduleVersionService).
            $table->unsignedBigInteger('academic_term_id');

            // User-given checkpoint label + optional description.
            $table->string('label', 100);
            $table->text('notes')->nullable();

            // Every tentative class_schedules row for the term at save time,
            // as raw attributes (JSON array of row-dicts) — same pattern as
            // ai_schedule_jobs.replaced_schedules / class_schedule_approval_batches.schedule_snapshot.
            $table->longText('schedule_snapshot');

            // Cached count so the list view doesn't need to decode the JSON.
            $table->integer('session_count')->default(0);

            // Audit
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->timestamps();

            $table->index('academic_term_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_versions');
    }
};
