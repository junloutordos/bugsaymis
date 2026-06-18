<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_time_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('face_enrollment_id')->nullable()->constrained('face_enrollments')->nullOnDelete();

            $table->date('work_date')->index();
            $table->enum('punch_type', ['time_in_am', 'time_out_am', 'time_in_pm', 'time_out_pm']);
            $table->timestamp('punched_at');

            // Captured frame (AWS-selected liveness reference frame), stored in S3
            $table->string('photo_s3_key')->nullable();

            // AWS Rekognition Face Liveness
            $table->string('liveness_session_id')->nullable();
            $table->decimal('liveness_confidence', 5, 2)->nullable();

            // AWS Rekognition CompareFaces (identity match against face_enrollments.s3_key)
            $table->decimal('match_score', 5, 2)->nullable();
            $table->enum('match_status', ['verified', 'rejected', 'manual_review'])->index();
            $table->string('failure_reason')->nullable()
                ->comment('e.g. liveness_failed, no_face_detected, multiple_faces, low_confidence');

            // Audit context
            $table->string('ip_address', 45)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'work_date', 'punch_type'], 'online_time_punches_unique');
            $table->index(['user_id', 'work_date']);
            $table->index(['match_status', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_time_punches');
    }
};
