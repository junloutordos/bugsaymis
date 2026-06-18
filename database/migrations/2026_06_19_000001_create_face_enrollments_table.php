<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('face_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Reference photo for facial matching
            $table->string('s3_key');

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();

            // Self-enrollment + Data Privacy Act consent
            $table->timestamp('consent_given_at')->nullable();
            $table->string('consent_ip', 45)->nullable();
            $table->timestamp('enrolled_at')->nullable();

            // HR review
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->boolean('is_active')->default(true)
                ->comment('False once superseded by a newer enrollment for this user');

            $table->timestamps();

            $table->index(['user_id', 'is_active', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_enrollments');
    }
};
