<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Co-teaching pivot for shared class records — currently only used by the
 * PEHM (PE / Health / Music) special case, where one class record per
 * section is jointly owned by up to three teachers, one per subject.
 *
 * class_records.teacher_id remains the primary/creator teacher and is left
 * untouched for backward compatibility; every existing single-teacher record
 * simply has zero rows here and all ownership checks fall back to
 * teacher_id, so nothing about non-PEHM behaviour changes.
 *
 * subject_id identifies which of the shared record's subjects (PE, Health,
 * or Music) this teacher owns — it gates which grading-category leaves and
 * which attendance dates they may write to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_record_teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_record_id')->constrained('class_records')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['class_record_id', 'subject_id'], 'class_record_teachers_record_subject_unique');
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_record_teachers');
    }
};
