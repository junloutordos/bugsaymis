<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_record_assessment_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('class_record_assessment_id')->nullable();
            $table->foreign('class_record_assessment_id', 'crad_assessment_fk')
                ->references('id')->on('class_record_assessments')->nullOnDelete();
            $table->unsignedBigInteger('class_record_quarter_id');
            $table->foreign('class_record_quarter_id', 'crad_quarter_fk')
                ->references('id')->on('class_record_quarters')->cascadeOnDelete();

            // Snapshot of the assessment at request time — stays legible even
            // after the assessment itself is deleted on approval.
            $table->string('title');
            $table->unsignedTinyInteger('assessment_number');
            $table->string('category_code')->nullable();
            $table->string('category_name')->nullable();
            $table->date('activity_date');
            $table->decimal('max_score', 8, 2);

            $table->unsignedBigInteger('requested_by_id');
            $table->foreign('requested_by_id', 'crad_requested_by_fk')
                ->references('id')->on('users');
            $table->text('reason');
            $table->string('status')->default('pending'); // pending | approved | rejected

            $table->unsignedBigInteger('reviewed_by_id')->nullable();
            $table->foreign('reviewed_by_id', 'crad_reviewed_by_fk')
                ->references('id')->on('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_remarks')->nullable();

            $table->timestamps();

            $table->index(['status', 'class_record_quarter_id'], 'crad_status_quarter_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_record_assessment_deletion_requests');
    }
};
