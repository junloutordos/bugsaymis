<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Date-specific overlays for exceptional school days (for example, a
     * Monday flag ceremony transferred to Tuesday after a holiday). The
     * weekly class_schedules and bell-schedule tables remain untouched.
     */
    public function up(): void
    {
        Schema::create('class_schedule_day_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_term_id')->constrained('academic_terms')->cascadeOnDelete();
            $table->date('postponed_from_date');
            $table->date('effective_date');
            $table->string('adjustment_type')->default('flag_ceremony');
            $table->time('ceremony_start_time')->default('07:30:00');
            $table->time('ceremony_end_time')->default('08:00:00');
            $table->unsignedSmallInteger('shift_minutes')->default(30);
            $table->string('reason');
            $table->enum('status', ['draft', 'published', 'cancelled'])->default('draft');
            $table->json('schedule_snapshot')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['academic_term_id', 'effective_date'], 'cs_day_adjustment_term_date_unique');
            $table->index(['status', 'effective_date'], 'cs_day_adjustment_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_schedule_day_adjustments');
    }
};
